<?php declare(strict_types=1);

namespace Erive\Delivery\Service;

use Doctrine\DBAL\Driver\PDO\Exception;
use Erive\Delivery\Api\CompanyApi;
use Erive\Delivery\Configuration;
use Erive\Delivery\EriveDelivery;
use Erive\Delivery\Model\Address;
use Erive\Delivery\Model\Customer;
use Erive\Delivery\Model\Parcel;
use GuzzleHttp\Client;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderService
{
    private string $eriveEnv;
    private string $apiKey;
    private string $apiTestKey;
    protected string $customParcelIdField;
    protected string $customStickerUrlField;
    protected string $customApiEndpoint;
    protected array $allowedDeliveryMethodIds;
    protected SystemConfigService $systemConfigService;
    protected EntityRepository $orderRepository;
    protected EntityRepository $orderDeliveryRepository;
    protected Context $context;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $orderRepository,
        EntityRepository $orderDeliveryRepository
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;

        $this->allowedDeliveryMethodIds = $this->systemConfigService->get('EriveDelivery.config.deliveryMethods') ?? [];
        $this->eriveEnv = $this->systemConfigService->get('EriveDelivery.config.eriveEnvironment');
        $this->apiKey = $this->systemConfigService->get('EriveDelivery.config.apiKey') ?? '';
        $this->apiTestKey = $this->systemConfigService->get('EriveDelivery.config.apiTestKey') ?? '';
        $this->customApiEndpoint = $this->systemConfigService->get('EriveDelivery.config.customApiEndpoint') ?? '';
        $this->customParcelIdField = EriveDelivery::FIELD_PARCEL_ID;
        $this->customStickerUrlField = EriveDelivery::FIELD_STICKER_URL;
        $this->context = Context::createDefaultContext();

        if (empty($this->apiKey)) {
            dd('Api key not set in configuration. Exiting');
        }
    }

    protected function writeTrackingNumber($orderId, $trackingCode)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('deliveries');
        $delivery = $this->orderRepository->search($criteria, $this->context)->getEntities()->first()->getDeliveries();

        $trackingCodes = count($delivery) > 0 ? $delivery->first()->getTrackingCodes() : [];
        $trackingCodes[] = $trackingCode;
        $this->orderDeliveryRepository->update([['id' => $delivery->first()->getId(), 'trackingCodes' => $trackingCodes]], $this->context);
    }

    protected function getUnsubmittedOrders(): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('customFields.' . $this->customParcelIdField, null),
                new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                new EqualsAnyFilter('deliveries.shippingMethodId', $this->allowedDeliveryMethodIds),
            ])
        );
        $criteria->addAssociations([
            'lineItems.product',
            'orderCustomer.customer',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingMethod',
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

    protected function needsLabel($item): bool
    {
        if (
            $item->getType() !== 'product' || 
            !empty($item->getParentId())
        ) {
            return false;
        }
        
        return true;
    }

    protected function populateEriveDeliveryParcel($order): Parcel
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

                if ($this->needsLabel($item)) {
                    $totalPackagingUnits += $quantity;
                }
                $parcelWeight += ($quantity * $prodWeight);
                $parcelVolume += (floatval($prodWidth / 1000) * floatval($prodHeight / 1000) * floatval($prodLength / 1000)) * $quantity;
            }
        }

        // Overwrite weight untill API allows heavy parcels
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

    protected function publishParcelToEriveDelivery(Parcel $parcel)
    {
        if ($this->eriveEnv == "www"){
            $config = Configuration::getDefaultConfiguration()->setApiKey('key', $this->apiKey);
        } else {
            $config = Configuration::getDefaultConfiguration()->setApiKey('key', $this->apiTestKey);
        }
        
        if ($this->eriveEnv == "custom"){
            $config->setHost($this->customApiEndpoint);
        } else {
            $config->setHost("https://" . $this->eriveEnv .".greentohome.at/api/v1");
        }

        try {
            // Save parcel to ERIVE.delivery and retrieve assigned ID and Label URL
            $apiInstance = new CompanyApi(new Client, $config);
            return $apiInstance->submitParcel($parcel);
        } catch (Exception $e) {
            dump('Exception when processing order number :' . $parcel->getExternalReference() . PHP_EOL . $e->getMessage() . PHP_EOL);
        }

        return;
    }

    protected function populateParcelData($order)
    {
        $preparedParcel = $this->populateEriveDeliveryParcel($order);
        $pubParcel = $this->publishParcelToEriveDelivery($preparedParcel);
        if ($pubParcel === null || !$pubParcel['success']) { 
            dump('Parcel not returned from API');
            return;
        }

        $eriveParcelId = $pubParcel->getParcel()->getId();
        $eriveStickerUrl = $pubParcel->getParcel()->getLabelUrl();

        // Set custom fields to ERIVE.delivery Parcel ID and Sticker URL
        $customFields = $order->getCustomFields();
        $customFields[$this->customParcelIdField] = $eriveParcelId;
        $customFields[$this->customStickerUrlField] = $eriveStickerUrl;
        $this->writeTrackingNumber($order->getId(), $eriveParcelId);

        // TODO : set order status to "In Progress"
        $this->orderRepository->update([['id' => $order->getId(), 'customFields' => $customFields]], $this->context);

        dump('Order #' . $order->getOrderNumber() . ' -> Erive-Paketnummer: ' . $eriveParcelId . PHP_EOL);
    }

    protected function processOrder($order): void
    {
        $customFields = $order->getCustomFields();
        if (is_array($customFields) && array_key_exists('isReturnOrder', $customFields) && $customFields['isReturnOrder']) {
            dump('Order #' . $order->getOrderNumber() . ' skipped (return order)' . PHP_EOL);
            return;
        }
        
        $orderDeliveryMethodIds = $order->getDeliveries()->getShippingMethodIds();
        $wrongShipping = true;

        foreach ($orderDeliveryMethodIds as $orderDeliveryMethodId) {
            if (in_array($orderDeliveryMethodId, $this->allowedDeliveryMethodIds)) {
                $wrongShipping = false;
            }
        }

        if ($wrongShipping) {
            dump(`Order #{$order->getOrderNumber()} skipped (wrong shipping method)` . PHP_EOL);
            return;
        }

        $this->populateParcelData($order);
    }

    public function processAllOrders(): void
    {
        $orders = $this->getUnsubmittedOrders();

        if (count($orders) === 0) {
            dump('No new unhandled orders found' . PHP_EOL);
            return;
        }

        dump('Following orders are being processed:' . PHP_EOL);

        foreach ($orders as $order) {
            $this->processOrder($order);
        }
    }

    public function processOrderById(string $id): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new AndFilter([
                new EqualsFilter('customFields.' . $this->customParcelIdField, null),
                new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                new EqualsAnyFilter('deliveries.shippingMethodId', $this->allowedDeliveryMethodIds),
                new EqualsFilter('id', $id),
            ])
        );
        $criteria->addAssociations([
            'lineItems.product',
            'orderCustomer.customer',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingMethod',
            'transactions.stateMachineState.technicalName',
        ]);

        $order = null;
        try {
            $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
        } catch (Exception $e) {
            dump('Exception when searching for unsyncronized orders: ');
            dump($e->getMessage());
        }

        if (is_null($order)) {
            dump('No orders found with id ' . $id . ', or order does not satisfy requirements');
            return;
        }

        $this->processOrder($order);
    }
}