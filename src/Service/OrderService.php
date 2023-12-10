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
    protected Configuration $config;
    protected CompanyApi $companyApi;

    protected SystemConfigService $systemConfigService;
    protected EntityRepository $orderRepository;
    protected EntityRepository $orderDeliveryRepository;
    protected LoggerInterface $logger;

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

        $this->config = Configuration::getDefaultConfiguration();
    }

    public function processAllOrders(): void
    {
        if (!$this->isApiKeySet()) {
            return;
        }

        $orders = $this->getUnsubmittedOrders();

        if (count($orders) === 0) {
            $this->log('info', 'No new unhandled orders found');
            return;
        }

        foreach ($orders as $order) {
            $this->processOrder($order);
        }
    }

    public function processOrderById(string $orderId): void
    {
        $order = $this->getOrderById($orderId);

        if (is_null($order)) {
            $this->log('info', 'Order with id ' . $orderId . ' not processed');
            return;
        }

        $this->log('info', 'Processing order # ' . $order->getOrderNumber() . ' (id: ' . $orderId . ')');
        $this->processOrder($order);
    }

    protected function isApiKeySet($key = 'key'): bool
    {
        if (empty($this->config->getApiKey($key))) {
            $this->log('critical', 'API key not set in configuration');
            return false;
        }

        return true;
    }

    protected function readTrackingNumbers(string $orderId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('deliveries');
        $context = Context::createDefaultContext();

        $deliveries = $this->orderRepository->search($criteria, $context)->getEntities()->first()->getDeliveries();

        $trackingCodes = [];
        foreach ($deliveries as $delivery) {
            $trackingCodes = array_merge($trackingCodes, $delivery->getTrackingCodes());
        }
        return $trackingCodes;
    }

    protected function writeTrackingNumber(string $orderId, string $trackingCode)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('deliveries');
        $context = Context::createDefaultContext();

        $delivery = $this->orderRepository->search($criteria, $context)->getEntities()->first()->getDeliveries();

        $trackingCodes = count($delivery) > 0 ? $delivery->first()->getTrackingCodes() : [];
        $trackingCodes[] = $trackingCode;
        $this->orderDeliveryRepository->update([['id' => $delivery->first()->getId(), 'trackingCodes' => $trackingCodes]], $context);
    }

    protected function removeTrackingNumber(string $orderId, string $trackingCode)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('deliveries');
        $context = Context::createDefaultContext();

        $delivery = $this->orderRepository->search($criteria, $context)->getEntities()->first()->getDeliveries();

        $trackingCodes = count($delivery) > 0 ? $delivery->first()->getTrackingCodes() : [];

        $this->orderDeliveryRepository->update(
            [[
                'id' => $delivery->first()->getId(),
                'trackingCodes' => array_filter($trackingCodes, fn($tc) => $tc !== $trackingCode)
            ]],
            $context
        );
    }

    protected function needsLabel($item): bool
    {
        if ($item->getType() !== 'product' || !empty($item->getParentId())) {
            return false;
        }

        return true;
    }

    protected function getCriteriaFilter($args = [])
    {
        return new AndFilter(array_merge([
            new OrFilter([
                new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                new EqualsFilter('deliveries.stateMachineState.technicalName', 'shipped'),
            ])
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
        ], $args);
    }

    protected function getUnsubmittedOrders(): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->getCriteriaFilter());
        $criteria->addAssociations($this->getCriteriaAssociations());

        try {
            return $this->orderRepository->search($criteria, Context::createDefaultContext());
        } catch (Exception $e) {
            $this->log('critical', 'Exception when searching for unsynchronized orders: ', $e->getMessage());
        }
    }

    protected function announceParcel($parcelId): void
    {
        try {
            $this->companyApi->updateParcelById($parcelId, ['status' => Parcel::STATUS_ANNOUNCED]);
            $this->log('info', 'Parcel number ' . $parcelId . ' changed status to "' . Parcel::STATUS_ANNOUNCED . '"');
        } catch (ApiException $e) {
            $this->log('critical', 'Unable to change parcel status to "' . Parcel::STATUS_ANNOUNCED . '" : ' . $e->getMessage());
        }
    }

    protected function getOrderById(string $orderId)
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->getCriteriaFilter([new EqualsFilter('id', $orderId)]));
        $criteria->addAssociations($this->getCriteriaAssociations());

        return $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }

    protected function populateEriveDeliveryParcel($order, $announceOnShip): Parcel
    {
        $shippingAddress = $order->getDeliveries()->first()->getShippingOrderAddress();
        $countPackagingUnits = $this->systemConfigService->get('EriveDelivery.config.countPackagingUnits', $order->getSalesChannelId()) ?? false;

        $parcelWidth = 0;
        $parcelLength = 0;
        $parcelHeight = 0;
        $parcelWeight = 0;
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

                if (intval($parcelWeight) > 31) {
                    $parcelWeight = 30.99;
                }
            }
        }

        $parcel = new Parcel();
        $parcel->setExternalReference($order->getOrderNumber());
        $parcel->setComment($order->getCustomerComment() ?: '');
        $parcel->setWeight($parcelWeight);
        $parcel->setWidth($parcelWidth);
        $parcel->setLength($parcelLength);
        $parcel->setHeight($parcelHeight);
        $parcel->setPackagingUnits($countPackagingUnits ? $totalPackagingUnits : 1);

        $orderDeliveryStatus = $order->getDeliveries()->first()->getStateMachineState()->getTechnicalName();
        if ($announceOnShip && ($orderDeliveryStatus === 'shipped')) {
            $parcel->setStatus(Parcel::STATUS_ANNOUNCED);
        }

        $customer = new Customer();
        $customer->setName(strval($order->getOrderCustomer()->getCustomer()));
        $customer->setEmail($order->getOrderCustomer()->getEmail());
        $customer->setPhone($shippingAddress->getPhoneNumber() ?: '0');

        $customerAddress = new Address();
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
        try {
            return $this->companyApi->submitParcel($parcel);
        } catch (ApiException $e) {
            $this->log('critical', sprintf('Exception when processing order number :%s %s', $parcel->getExternalReference(), $e->getMessage()));
        }
    }

    protected function processOrderWithParcelData(OrderEntity $order): void
    {
        $trackingNumbers = $this->readTrackingNumbers($order->getId());
        $announceOnShip = $this->systemConfigService->get('EriveDelivery.config.announceParcelOnShip', $order->getSalesChannelId()) ?? false;

        foreach ($trackingNumbers as $trackingNumber) {
            try {
                $pubParcel = $trackingNumber ? ($this->companyApi->getParcelById($trackingNumber) ?? null) : null;
            } catch(ApiException $e) {
                switch (intval($e->getCode())) {
                    case 404:
                        $this->log('error', 'Parcel ' . $trackingNumber . ' does not exist');
                        $this->removeTrackingNumber($order->getId(), $trackingNumber);
                        break;
                    case 400:
                    case 401:
                    case 403:
                    case 500:
                        $this->log('critical', 'API Error! ' . $e->getMessage());
                        return;
                    default:
                        break;
                }
                $pubParcel = null;
            }

            if ($pubParcel) {
                $parcelId = $trackingNumber;
                break;
            }
        }

        if (!isset($pubParcel)) {
            $preparedParcel = $this->populateEriveDeliveryParcel($order, $announceOnShip);
            $pubParcel = $this->publishParcelToEriveDelivery($preparedParcel);

            if ($pubParcel === null || !$pubParcel['success']) {
                $this->log('critical', 'Parcel not returned from API');
                return;
            }

            $pubParcel = $pubParcel->getParcel();
            $parcelId = $pubParcel->getId();
            $eriveStickerUrl = $pubParcel->getLabelUrl();

            $customFields = $order->getCustomFields() ?? [];
            $customFields[EriveDelivery::CUSTOM_FIELD_PARCEL_NUMBER] = $parcelId;
            $customFields[EriveDelivery::CUSTOM_FIELD_PARCEL_LABEL_URL] = $eriveStickerUrl;

            $this->orderRepository->update([['id' => $order->getId(), 'customFields' => $customFields]], Context::createDefaultContext());
            $this->writeTrackingNumber($order->getId(), $parcelId);

            $msg = 'Order #' . $order->getOrderNumber() . ' -> parcel number: ' . $parcelId;
            if (
                $announceOnShip &&
                $preparedParcel->getStatus() === Parcel::STATUS_ANNOUNCED &&
                $order->getDeliveries()->first()->getStateMachineState()->getTechnicalName() === 'shipped'
            ) {
                $msg .= ', status set to "' . Parcel::STATUS_ANNOUNCED . '"';
            }
            $this->log('info', $msg);
        }

        if (
            $announceOnShip &&
            $pubParcel->getStatus() === Parcel::STATUS_PREPARED_BY_SENDER &&
            $order->getDeliveries()->first()->getStateMachineState()->getTechnicalName() === 'shipped'
        ) {
            $this->announceParcel($parcelId);
        }
    }

    protected function processOrder(OrderEntity $order): void
    {
        if ($this->isReturnOrder($order)) {
            $this->log('info', 'Order #' . $order->getOrderNumber() . ' skipped (return order)');
            return;
        }

        $eriveEnv = $this->systemConfigService->get('EriveDelivery.config.eriveEnvironment', $order->getSalesChannelId()) ?? 'www';
        $apiKey = $this->systemConfigService->get('EriveDelivery.config.apiKey', $order->getSalesChannelId()) ?? '';
        $apiTestKey = $this->systemConfigService->get('EriveDelivery.config.apiTestKey', $order->getSalesChannelId()) ?? '';
        $customApiEndpoint = $this->systemConfigService->get('EriveDelivery.config.customApiEndpoint', $order->getSalesChannelId()) ?? '';

        switch ($eriveEnv) {
            case 'www':
                $this->config->setHost('https://' . $eriveEnv . '.ERIVE.Delivery/api/v1');
                $this->config->setApiKey('key', $apiKey);
                break;
            case 'custom':
                $this->config->setHost($customApiEndpoint);
                $this->config->setApiKey('key', $apiTestKey);
                break;
            default:
                $this->config->setHost('https://' . $eriveEnv . '.greentohome.at/api/v1');
                $this->config->setApiKey('key', $apiTestKey);
                break;
        }

        if (!$this->isApiKeySet()) {
            return;
        }

        $allowedDeliveryMethodIds = $this->systemConfigService->get('EriveDelivery.config.deliveryMethods', $order->getSalesChannelId()) ?? [];
        $this->companyApi = new CompanyApi(new Client(), $this->config);

        foreach ($order->getDeliveries()->getShippingMethodIds() as $orderDeliveryMethodId) {
            if (in_array($orderDeliveryMethodId, $allowedDeliveryMethodIds)) {
                $this->processOrderWithParcelData($order);
                return;
            }
        }

        $this->log('info', 'Order #' . $order->getOrderNumber() . ' skipped as shipping method is not whitelisted');
    }

    protected function log(string $level, string $msg):void
    {
        $this->logger->$level('ERIVE.Delivery: ' . $msg);
    }

    protected function isReturnOrder(OrderEntity $order): bool
    {
        $customFields = $order->getCustomFields();
        return is_array($customFields) && array_key_exists('isReturnOrder', $customFields) && $customFields['isReturnOrder'];
    }
}
