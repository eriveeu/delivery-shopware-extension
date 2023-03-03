<?php 
declare(strict_types=1);

namespace NewMobilityEnterprise\Service;

use GuzzleHttp\Client;
use GreenToHome\Configuration;

use GreenToHome\Api\CompanyApi;
use GreenToHome\Model\Parcel;
use GreenToHome\Model\Customer;
use GreenToHome\Model\Address;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ShopWareToGTH
{
    private String $gthEnv;
    private String $apiKey;
    private String $customParcelIdField;
    private String $customStickerUrlField;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository,
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        
        $this->gthEnv = $this->systemConfigService->get('GreenToHome.config.gthEnvironment');
        $this->apiKey = $this->systemConfigService->get('GreenToHome.config.apikey');
        $this->customParcelIdField = $this->systemConfigService->get('GreenToHome.config.parcelIdFieldName') ?? 'custom_gth_ParcelID';
        $this->customStickerUrlField = $this->systemConfigService->get('GreenToHome.config.stickerUrlFieldName') ?? 'custom_gth_StickerUrl';
    }

    private function getUnsubmittedOrders(): EntitySearchResult
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter( 'customFields.' . $this->customParcelIdField, null ),
                new EqualsFilter( 'transactions.stateMachineState.technicalName', 'paid'),
            ])
        );
        $criteria->addAssociations([
            'lineItems.product',
            'orderCustomer.customer',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddress.country',
            'transactions.stateMachineState.technicalName'
        ]);

        $results = null;
        try {
            $results = $this->orderRepository->search($criteria, $context);
        } catch (Exception $e) {
            dump ('Exception when searching for unsyncronized orders: ');
            dump (e->getMessage());
        }

        return $results;
    }

    private function populateGthParcel($order) 
    {
        $shippingAddress = $order->getDeliveries()->first()->getShippingOrderAddress();

        $totalWeight = 0;
        $totalVolume = 0;
        $totalPackagingUnits = 0;

        foreach($order->getLineItems()->getElements() as $item) {
            $quantity = intval($item->getQuantity());
            $prodWeight = floatval($item->getProduct()->getWeight());
            $prodWidth = intval($item->getProduct()->getWidth());
            $prodLength = intval($item->getProduct()->getLength());
            $prodHeight = intval($item->getProduct()->getHeight());

            $totalPackagingUnits += $quantity;
            $totalWeight += ($quantity * $prodWeight);
            $totalVolume += (floatval($prodWidth / 1000) * floatval($prodHeight / 1000) * floatval($prodLength / 1000)) * $quantity;
        }


        $parcelWidth = '0';
        $parcelLenght = '0';
        $parcelHeight = '0';

        // Configuring Parcel
        $parcel = new Parcel(); // \GreenToHome\Model\Parcel | Parcel to submit
        $parcel->setExternalReference($order->getOrderNumber());
        // Sets the comment to parcel volume if there is no comment defined
        $parcel->setComment($order->getCustomerComment() ?: 'Package volume: ' . $totalVolume . 'm^3');
        $parcel->setWeight($totalWeight);
        $parcel->setWidth($parcelWidth); // ######### - How to calculate for multiple items? - #########
        $parcel->setLenght($parcelLenght); // ######### - How to calculate for multiple items? - #########
        $parcel->setHeight($parcelHeight); // ######### - How to calculate for multiple items? - #########
        $parcel->setPackagingUnits($totalPackagingUnits);

        $customer = new Customer(); // \GreenToHome\Model\Customer
        $customer->setName($order->getOrderCustomer()->getFirstName() . ' ' . $order->getOrderCustomer()->getLastName());
        $customer->setEmail($order->getOrderCustomer()->getEmail());
        $customer->setPhone($shippingAddress->getPhoneNumber() ?? '+43555000000');

        $customerAddress = new Address(); // \GreenToHome\Model\Address
        $customerAddress->setCountry($shippingAddress->getCountry()->getIso());
        $customerAddress->setCity($shippingAddress->getCity());
        $customerAddress->setZip($shippingAddress->getZipcode());
        $customerAddress->setStreet($shippingAddress->getStreet());
        $customerAddress->setStreetNumber('0');  // TODO extract street number from street or modify ShopWare to include this field
        $a1 = $shippingAddress->getAdditionalAddressLine1() ?: '';
        $a2 = $shippingAddress->getAdditionalAddressLine2() ?: '';
        $customerAddress->setComment($a1 . ($a1 && $a2 ? ', ' : '') . $a2); // Set address comment as a union of additional address lines

        $customer->setAddress($customerAddress);
        $parcel->setTo($customer);

        return $parcel;
    }

    private function publishParcelToGth($parcel)
    {
        $config = Configuration::getDefaultConfiguration()->setApiKey('key', $this->apiKey);

        try {
            // Save parcel to GTH and retrieve assigned ID and Sticker URL
            $apiInstance = new CompanyApi(new \GuzzleHttp\Client, $config);
            return $apiInstance->submitParcel($parcel);
        } catch (Exception $e) {
            print_r('Exception when processing order number :' . $order->orderNumber . PHP_EOL . $e->getMessage() . PHP_EOL);
        }

        return;
    }

    public function processAllOrders(): int
    {
        $context = Context::createDefaultContext();

        $orders = $this->getUnsubmittedOrders();

        if (count($orders) === 0) { print_r('No new unhandled orders found' . PHP_EOL); }
        else { print_r('Following orders are being processed:' . PHP_EOL); }

        foreach ($orders as $order) {
            $prepParcel = $this->populateGthParcel($order);
            if ($this->gthEnv !== 'prod') { dump($prepParcel); }
            $pubParcel = $this->publishParcelToGth($prepParcel);

            $gthParcelId = $pubParcel->getParcel()->getId();
            $gthStickerUrl = $pubParcel->getParcel()->getLabelUrl();

            // Set custom fields to GTH Parcel ID and Sticker URL
            $customFields = $order->getCustomFields();
            $customFields[$this->customParcelIdField] = $gthParcelId;
            $customFields[$this->customStickerUrlField] = $gthStickerUrl;

            // TODO : set order status to "In Progress"
            $this->orderRepository->update([['id' => $order->getId(), 'customFields'=>$customFields]], $context);

            print_r('Order #' . $order->getOrderNumber() . ' -> GTH-Paketnummer: ' . $gthParcelId . PHP_EOL);
        }

        // Exit code 0 for success
        return 0;
    }
}
