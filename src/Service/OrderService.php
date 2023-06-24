<?php declare(strict_types=1);

namespace Erive\Delivery\Service;

use Doctrine\DBAL\Driver\PDO\Exception;
use Erive\Delivery\Api\CompanyApi;
use Erive\Delivery\Configuration;
use Erive\Delivery\Model\Address;
use Erive\Delivery\Model\Customer;
use Erive\Delivery\Model\Parcel;
use GuzzleHttp\Client;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderService
{
    private string $eriveEnv;
    private string $apiKey;
    private string $customParcelIdField;
    private string $customStickerUrlField;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;
    private Context $context;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        $this->eriveEnv = $systemConfigService->get('EriveDelivery.config.eriveEnvironment');
        $this->apiKey = $systemConfigService->get('EriveDelivery.config.apikey');
        $this->customParcelIdField = $systemConfigService->get('EriveDelivery.config.parcelIdFieldName') ?? 'custom_EriveDelivery_ParcelID';
        $this->customStickerUrlField = $systemConfigService->get('EriveDelivery.config.stickerUrlFieldName') ?? 'custom_EriveDelivery_StickerUrl';

        $this->context = Context::createDefaultContext();
    }

    private function getUnsubmittedOrders(): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('customFields.' . $this->customParcelIdField, null),
                new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
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
            $results = $this->orderRepository->search($criteria, $this->context);
        } catch (Exception $e) {
            dump('Exception when searching for unsynchronized orders: ');
            dump($e->getMessage());
        }

        return $results;
    }

    private function populateEriveDeliveryParcel($order): Parcel
    {
        $shippingAddress = $order->getDeliveries()->first()->getShippingOrderAddress();

        $parcelWidth = 0;
        $parcelLength = 0;
        $parcelHeight = 0;
        $parcelWeight = 0;
        $parcelVolume = 0;
        $totalPackagingUnits = 0;

        foreach ($order->getLineItems()->getElements() as $item) {
            if ($item->getProduct()) {
                $quantity = intval($item->getQuantity() ?: 1);
                $prodWeight = floatval($item->getProduct()->getWeight() ?: 0);
                $prodWidth = intval($item->getProduct()->getWidth() ?: 0);
                $prodLength = intval($item->getProduct()->getLength() ?: 0);
                $prodHeight = intval($item->getProduct()->getHeight() ?: 0);

                $parcelWidth = $parcelWidth > $prodWidth ?: $prodWidth;
                $parcelLength = $parcelLength > $prodLength ?: $prodLength;
                $parcelHeight += $prodHeight * $quantity;

                $totalPackagingUnits += $quantity;
                $parcelWeight += ($quantity * $prodWeight);
                $parcelVolume += (floatval($prodWidth / 1000) * floatval($prodHeight / 1000) * floatval($prodLength / 1000)) * $quantity;
            }
        }

        // Overwrite weight untill API is fixed
        $parcelWeight = 0;

        // Configuring Parcel
        $parcel = new Parcel(); // \Erive\Delivery\Api\Model\Parcel | Parcel to submit
        $parcel->setExternalReference($order->getOrderNumber());
        // Sets the comment to parcel volume if there is no comment defined
        $parcel->setComment($order->getCustomerComment() ?: 'Package volume: ' . $parcelVolume . 'm^3');
        $parcel->setWeight($parcelWeight);
        $parcel->setWidth($parcelWidth);
        $parcel->setLength($parcelLength);
        $parcel->setHeight($parcelHeight);
        $parcel->setPackagingUnits($totalPackagingUnits);

        $customer = new Customer(); // \Erive\Delivery\Api\Model\Customer
        $customer->setName($order->getOrderCustomer()->getFirstName() . ' ' . $order->getOrderCustomer()->getLastName());
        $customer->setEmail($order->getOrderCustomer()->getEmail());
        $customer->setPhone($shippingAddress->getPhoneNumber() ?: "0");

        $customerAddress = new Address(); // \Erive\Delivery\Api\Model\Address
        $customerAddress->setCountry($shippingAddress->getCountry()->getIso());
        $customerAddress->setCity($shippingAddress->getCity());
        $customerAddress->setZip($shippingAddress->getZipcode());
        $customerAddress->setStreet($shippingAddress->getStreet());
        $a1 = $shippingAddress->getAdditionalAddressLine1() ?: '';
        $a2 = $shippingAddress->getAdditionalAddressLine2() ?: '';
        $customerAddress->setComment($a1 . ($a1 && $a2 ? ', ' : '') . $a2); // Set address comment as a union of additional address lines

        $customer->setAddress($customerAddress);
        $parcel->setTo($customer);

        return $parcel;
    }

    private function publishParcelToEriveDelivery(Parcel $parcel)
    {
        $config = Configuration::getDefaultConfiguration()->setApiKey('key', $this->apiKey);

        try {
            // Save parcel to ERIVE.delivery and retrieve assigned ID and Sticker URL
            $apiInstance = new CompanyApi(new Client, $config);
            return $apiInstance->submitParcel($parcel);
        } catch (Exception $e) {
            print_r('Exception when processing order number :' . $parcel->getExternalReference() . PHP_EOL . $e->getMessage() . PHP_EOL);
        }

        return;
    }

    private function populateParcelData($order)
    {
        $preparedParcel = $this->populateEriveDeliveryParcel($order);
        $pubParcel = $this->publishParcelToEriveDelivery($preparedParcel);

        $eriveParcelId = $pubParcel->getParcel()->getId();
        $eriveStickerUrl = $pubParcel->getParcel()->getLabelUrl();

        // Set custom fields to ERIVE.delivery Parcel ID and Sticker URL
        $customFields = $order->getCustomFields();
        $customFields[$this->customParcelIdField] = $eriveParcelId;
        $customFields[$this->customStickerUrlField] = $eriveStickerUrl;

        // TODO : set order status to "In Progress"
        $this->orderRepository->update([['id' => $order->getId(), 'customFields' => $customFields]], $this->context);

        print_r('Order #' . $order->getOrderNumber() . ' -> Erive-Paketnummer: ' . $eriveParcelId . PHP_EOL);
    }

    public function processAllOrders(): void
    {
        $orders = $this->getUnsubmittedOrders();

        if (count($orders) === 0) {
            print_r('No new unhandled orders found' . PHP_EOL);
            return;
        }

        print_r('Following orders are being processed:' . PHP_EOL);

        foreach ($orders as $order) {
            $this->populateParcelData($order);
        }
    }

    public function processOrderById(string $id): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('customFields.' . $this->customParcelIdField, null),
                new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                new EqualsFilter('id', $id),
            ])
        );
        $criteria->addAssociations([
            'lineItems.product',
            'orderCustomer.customer',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddress.country',
            'transactions.stateMachineState.technicalName'
        ]);

        $order = null;
        try {
            $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        } catch (Exception $e) {
            dump('Exception when searching for unsyncronized orders: ');
            dump($e->getMessage());
        }

        if (is_null($order)) {
            return;
        }

        $this->populateParcelData($order);
    }
}