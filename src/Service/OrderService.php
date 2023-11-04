<?php

declare (strict_types=1);

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
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
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
    protected bool $announceOnShip;
    protected Configuration $config;
    protected CompanyApi $companyApi;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $orderRepository,
        EntityRepository $orderDeliveryRepository,
        LoggerInterface $logger
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
        $this->announceOnShip = $systemConfigService->get('EriveDelivery.config.announceParcelOnShip') ?? false;

        if (empty($this->apiKey)) {
            $this->logger->critical('API key not set in configuration.');
        }

        $config = Configuration::getDefaultConfiguration();
        switch ($this->eriveEnv) {
            case "www":
                $config->setHost("https://" . $this->eriveEnv . ".ERIVE.Delivery/api/v1");
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
        $this->config = $config;
        $this->companyApi = new CompanyApi(new Client(), $this->config);
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

    protected function removeTrackingNumber($orderId, $trackingCode)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('deliveries');
        $delivery = $this->orderRepository->search($criteria, $this->context)->getEntities()->first()->getDeliveries();

        $trackingCodes = count($delivery) > 0 ? $delivery->first()->getTrackingCodes() : [];
        unset($trackingCodes[$trackingCode]);
        $this->orderDeliveryRepository->update([['id' => $delivery->first()->getId(), 'trackingCodes' => $trackingCodes]], $this->context);
    }

    protected function getCriteriaFilter($args = [])
    {
        return new AndFilter(array_merge([
            new OrFilter([
                new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                new EqualsFilter('deliveries.stateMachineState.technicalName', 'shipped'),
            ]),
            new EqualsAnyFilter('deliveries.shippingMethodId', $this->allowedDeliveryMethodIds),
        ], $args));
    }

    protected function getCriteriaAssociations($args = [])
    {
        return array_merge([
            'lineItems.product',
            'orderCustomer.customer',
            'deliveries.shippingOrderAddress',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingMethod',
            'deliveries.stateMachineState.technicalName',
            'transactions.stateMachineState.technicalName',
        ]);
    }

    protected function getUnsubmittedOrders(): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->getCriteriaFilter());
        $criteria->addAssociations($this->getCriteriaAssociations());

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
        $a1 = $shippingAddress->getAdditionalAddressLine1() ?: '';
        $a2 = $shippingAddress->getAdditionalAddressLine2() ?: '';
        $customerAddress->setComment(\join(', ', [$a1, $a2])); // Set address comment as a union of additional address lines

        $customer->setAddress($customerAddress);
        $parcel->setTo($customer);

        return $parcel;
    }

    protected function publishParcelToEriveDelivery(Parcel $parcel)
    {
        try {
            return $this->companyApi->submitParcel($parcel);
        } catch (ApiException $e) {
            $this->logger->critical(sprintf("ERIVE.Delivery: Exception when processing order number :%s %s", $parcel->getExternalReference(), $e->getMessage()));
        }
    }

    protected function getCustomField($order, $fieldName, $default = null)
    {
        $customFields = $order->getCustomFields();
        if (is_array($customFields) and array_key_exists($fieldName, $customFields)) {
            return $customFields[$fieldName];
        }
        return $default;
    }

    protected function processOrderWithParcelData($order)
    {
        $parcelId = $this->getCustomField($order, $this->customParcelIdField, false);
        try {
            $pubParcel = $parcelId ? ($this->companyApi->getParcelById($parcelId) ?? null) : null;
        } catch(ApiException $e) {
            switch ($e->getCode()) {
                case 404:
                    $this->logger->error('ERIVE.Delivery: Parcel ' . $parcelId . ' does not exist');
                    $this->removeTrackingNumber($order->getId(), $parcelId);
                    // no break
                case 400:
                case 401:
                case 403:
                case 500:
                    $this->logger->critical('ERIVE.Delivery: API Error! ' . $e->getMessage());
                    // no break
                default:
                    $pubParcel = null;
                    break;
            }
        }

        if (!$pubParcel) {
            $preparedParcel = $this->populateEriveDeliveryParcel($order);
            $pubParcel = $this->publishParcelToEriveDelivery($preparedParcel);

            if ($pubParcel === null || !$pubParcel['success']) {
                $this->logger->critical("ERIVE.Delivery: Parcel not returned from API");
                return;
            }

            $pubParcel = $pubParcel->getParcel();
            $parcelId = $pubParcel->getId();
            $eriveStickerUrl = $pubParcel->getLabelUrl();

            // Set custom fields to ERIVE.Delivery Parcel ID and Shipping Label URL
            $customFields = $order->getCustomFields() ?? [];
            $customFields[$this->customParcelIdField] = $parcelId;
            $customFields[$this->customStickerUrlField] = $eriveStickerUrl;

            $this->orderRepository->update([['id' => $order->getId(), 'customFields' => $customFields]], $this->context);
            $this->writeTrackingNumber($order->getId(), $parcelId);

            $this->logger->info('ERIVE.Delivery: Order #' . $order->getOrderNumber() . ' -> parcel number: ' . $parcelId);
        }

        if ($this->announceOnShip && $order->getDeliveries()->first()->getStateMachineState()->getTechnicalName() === 'shipped') {
            $this->announceParcel($parcelId);
        }
    }

    protected function processOrder($order): void
    {
        if ($this->getCustomField($order, 'isReturnOrder')) {
            $this->logger->info('ERIVE.Delivery: Order #' . $order->getOrderNumber() . ' skipped (return order)');
            return;
        }

        foreach ($order->getDeliveries()->getShippingMethodIds() as $orderDeliveryMethodId) {
            if (in_array($orderDeliveryMethodId, $this->allowedDeliveryMethodIds)) {
                $this->processOrderWithParcelData($order);
                return;
            }
        }

        $this->logger->info(`ERIVE.Delivery: Order #{$order->getOrderNumber()} skipped as shipping method is not whitelisted`);
    }

    protected function announceParcel($parcelId)
    {
        try {
            $this->companyApi->updateParcelById($parcelId, ["status" => Parcel::STATUS_ANNOUNCED]);
            $this->logger->info("ERIVE.Delivery: parcel number " . $parcelId . " changed status to '" . Parcel::STATUS_ANNOUNCED . "'");
        } catch (ApiException $e) {
            $this->logger->critical("ERIVE.Delivery: Unable to change parcel status to '" . Parcel::STATUS_ANNOUNCED . "' : " . $e->getMessage());
        }
    }

    public function processAllOrders(): void
    {
        $orders = $this->getUnsubmittedOrders();

        if (count($orders) === 0) {
            $this->logger->debug('ERIVE.Delivery: No new unhandled orders found');
            return;
        }

        foreach ($orders as $order) {
            $this->processOrder($order);
        }
    }

    protected function getOrderById(string $orderId)
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->getCriteriaFilter([new EqualsFilter('id', $orderId)]));
        $criteria->addAssociations($this->getCriteriaAssociations());

        return $this->orderRepository->search($criteria, $this->context)->getEntities()->first();
    }

    public function processOrderById(string $orderId): void
    {
        $order = $this->getOrderById($orderId);

        if (is_null($order)) {
            return;
        }

        $orderNumber = $order->getOrderNumber();
        $this->logger->info('ERIVE.Delivery: Processing order # ' . $orderNumber . ' (ID: ' . $orderId . ')');
        $this->processOrder($order);
    }
}
