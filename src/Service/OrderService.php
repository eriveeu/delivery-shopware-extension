<?php

declare(strict_types=1);

namespace Erive\Delivery\Service;

use Doctrine\DBAL\Driver\PDO\Exception;
use Erive\Delivery\Api\CompanyApi;
use Erive\Delivery\ApiException;
use Erive\Delivery\Configuration;
use Erive\Delivery\EriveDelivery;
use Erive\Delivery\Model\Address;
use Erive\Delivery\Model\Customer;
use Erive\Delivery\Model\Parcel;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
        EntityRepository    $orderRepository,
        EntityRepository    $orderDeliveryRepository,
        LoggerInterface     $logger
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->logger = $logger;

        $this->allowedDeliveryMethodIds = $systemConfigService->get('EriveDelivery.config.deliveryMethods') ?? [];
        $this->eriveEnv = $systemConfigService->get('EriveDelivery.config.eriveEnvironment');
        $this->apiKey = $systemConfigService->get('EriveDelivery.config.apiKey') ?? '';
        $this->apiTestKey = $systemConfigService->get('EriveDelivery.config.apiTestKey') ?? '';
        $this->customApiEndpoint = $systemConfigService->get('EriveDelivery.config.customApiEndpoint') ?? '';
        $this->customParcelIdField = EriveDelivery::CUSTOM_FIELD_PARCEL_NUMBER;
        $this->customStickerUrlField = EriveDelivery::CUSTOM_FIELD_PARCEL_LABEL_URL;
        $this->context = Context::createDefaultContext();

        if (empty($this->apiKey)) {
            $this->logger->critical('API key not set in configuration.');
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
            $this->logger->critical('Exception when searching for unsynchronized orders: ', $e->getMessage());
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

        // Overwrite weight until API allows heavy parcels
        $parcelWeight = 0;

        // Configuring Parcel
        $parcel = new Parcel(); // \Erive\Delivery\Api\Model\Parcel | Parcel to submit
        $parcel->setExternalReference($order->getOrderNumber());
        // Sets the comment to parcel volume if there is no comment defined
        $parcel->setComment($order->getCustomerComment() ?: "");
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
        $customerAddress->setStreetNumber(preg_replace('/^.*?(?=\d)/', '', $shippingAddress->getStreet()));
        if (!empty($shippingAddress->getAdditionalAddressLine1())) {
            $customerAddress->setComment((empty($customerAddress->getComment()) ? '' :  $customerAddress->getComment() . ', ') . $shippingAddress->getAdditionalAddressLine1());
        }
        if (!empty($shippingAddress->getAdditionalAddressLine2())) {
            $customerAddress->setComment((empty($customerAddress->getComment()) ? '' :  $customerAddress->getComment() . ', ') . $shippingAddress->getAdditionalAddressLine2());
        }

        $customer->setAddress($customerAddress);
        $parcel->setTo($customer);

        return $parcel;
    }

    protected function publishParcelToEriveDelivery(Parcel $parcel)
    {
        $config = Configuration::getDefaultConfiguration();

        switch ($this->eriveEnv) {
            case "www":
                $config->setHost("https://" . $this->eriveEnv . ".erive.delivery/api/v1");
                $config->setApiKey('key', $this->apiKey);
                break;
            case "custom":
                $config->setHost($this->customApiEndpoint);
                $config->setApiKey('key', $this->apiTestKey);
                break;
            default:
                $config->setHost("https://" . $this->eriveEnv . ".greentohome.at/api/v1");
                $config->setApiKey('key', $this->apiTestKey);
                break;
        }

        try {
            // Save parcel to ERIVE.delivery and retrieve assigned ID and Label URL
            $apiInstance = new CompanyApi(new Client(), $config);
            return $apiInstance->submitParcel($parcel);
        } catch (ApiException $e) {
            $this->logger->critical(sprintf("Exception when processing order number :%s %s", $parcel->getExternalReference(), $e->getMessage()));
        }
    }

    protected function populateParcelData($order)
    {
        $preparedParcel = $this->populateEriveDeliveryParcel($order);
        $pubParcel = $this->publishParcelToEriveDelivery($preparedParcel);
        if ($pubParcel === null || !$pubParcel['success']) {
            $this->logger->info("Parcel not returned from API");
            return;
        }

        $eriveParcelId = $pubParcel->getParcel()->getId();
        $eriveStickerUrl = $pubParcel->getParcel()->getLabelUrl();

        // Set custom fields to ERIVE.delivery Parcel ID and Shipping Label URL
        $customFields = $order->getCustomFields();
        $customFields[$this->customParcelIdField] = $eriveParcelId;
        $customFields[$this->customStickerUrlField] = $eriveStickerUrl;
        $this->orderRepository->update([['id' => $order->getId(), 'customFields' => $customFields]], $this->context);
        $this->writeTrackingNumber($order->getId(), $eriveParcelId);

        $this->logger->info('Order #' . $order->getOrderNumber() . ' -> ERIVE.delivery parcel number: ' . $eriveParcelId);
    }

    protected function processOrder($order): void
    {
        $customFields = $order->getCustomFields();
        if (is_array($customFields) && array_key_exists('isReturnOrder', $customFields) && $customFields['isReturnOrder']) {
            $this->logger->info('Order #' . $order->getOrderNumber() . ' skipped (return order)');
            return;
        }

        foreach ($order->getDeliveries()->getShippingMethodIds() as $orderDeliveryMethodId) {
            if (in_array($orderDeliveryMethodId, $this->allowedDeliveryMethodIds)) {
                $this->populateParcelData($order);
                return;
            }
        }

        $this->logger->info(`Order #{$order->getOrderNumber()} skipped (as shipping method is not whitelisted)`);
    }

    public function processAllOrders(): void
    {
        $orders = $this->getUnsubmittedOrders();

        if (count($orders) === 0) {
            $this->logger->debug('No new unhandled orders found');
            return;
        }

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

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->first();

        if (is_null($order)) {
            $this->logger->info('No orders found with id ' . $id . ', or order does not satisfy requirements');
            return;
        }

        $this->processOrder($order);
    }
}
