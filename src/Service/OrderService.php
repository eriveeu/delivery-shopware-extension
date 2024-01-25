<?php

declare(strict_types=1);

namespace Erive\Delivery\Service;

use Doctrine\DBAL\Driver\PDO\Exception;
use Erive\Delivery\Api\CompanyApi;
use Erive\Delivery\ApiException;
use Erive\Delivery\Configuration;
use Erive\Delivery\EriveDelivery;
use Erive\Delivery\Model\Address;
use Erive\Delivery\Model\CreatedParcel;
use Erive\Delivery\Model\Customer;
use Erive\Delivery\Model\Parcel;
use Erive\Delivery\Model\ParcelStatus;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
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
        $orders = $this->getUnsubmittedOrders();

        if ($orders->count() === 0) {
            $this->log('info', 'No new unhandled orders found');
            return;
        }

        /** @var OrderEntity $order */
        foreach ($orders as $order) {
            try {
                $this->processOrder($order);
            } catch (\Throwable $th) {
                $this->log('error', 'Unable to process order ' . $order->getOrderNumber() . ': ' . $th->getMessage());
            }
        }
    }

    public function processOrderById(string $orderId): void
    {
        try {
            $order = $this->getOrderById($orderId);
        } catch (Exception $e) {
            $this->log('info', 'Order with id ' . $orderId . ' not processed');
            return;
        }

        try {
            $this->processOrder($order);
        } catch (\Throwable $th) {
            $this->log('error', 'Unable to process order' . $order->getOrderNumber() . ': ' . $th->getMessage());
        }
    }

    protected function isApiKeySet(?string $salesChannelId = null, string $key = 'key'): bool
    {
        if (empty($this->config->getApiKey($key))) {
            $this->log('critical', 'API key not set in configuration for sales channel "' . $salesChannelId . '"');
            return false;
        }

        return true;
    }

    /**
     * @param string $orderId The ID of the order
     * @return array<string> The tracking numbers associated with the order
     */
    protected function readTrackingNumbers(string $orderId): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('deliveries');
        $context = Context::createDefaultContext();

        $deliveries = $this->orderRepository->search($criteria, $context)->getEntities()->first()->getDeliveries();

        $trackingCodes = [];
        /** OrderDeliveryEntity $delivery */
        foreach ($deliveries as $delivery) {
            $trackingCodes = array_merge($trackingCodes, $delivery->getTrackingCodes());
        }
        return $trackingCodes;
    }

    protected function writeTrackingNumber(string $orderId, string $trackingCode): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $orderId));
        $criteria->addAssociation('deliveries');
        $context = Context::createDefaultContext();

        $delivery = $this->orderRepository->search($criteria, $context)->getEntities()->first()->getDeliveries();

        $trackingCodes = count($delivery) > 0 ? array_filter($delivery->first()->getTrackingCodes(), fn($tc) => $tc !== $trackingCode) : [];
        $trackingCodes[] = $trackingCode;
        $this->orderDeliveryRepository->update([['id' => $delivery->first()->getId(), 'trackingCodes' => $trackingCodes]], $context);
    }

    protected function removeTrackingNumber(string $orderId, string $trackingCode): void
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

    protected function needsLabel(OrderLineItemEntity $item): bool
    {
        if ($item->getType() !== 'product' || !empty($item->getParentId())) {
            return false;
        }

        return true;
    }

    /**
     * Returns a default criteria Filter to apply to every search
     *
     * @param array<Filter> $args additinal filters to include
     * @return Filter
     */
    protected function getCriteriaFilter(array $args = []): Filter
    {
        return new AndFilter(array_merge([
            new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
            new OrFilter([
                new EqualsFilter('deliveries.stateMachineState.technicalName', 'open'),
                new EqualsFilter('deliveries.stateMachineState.technicalName', 'shipped'),
            ])
        ], $args));
    }

    /**
     * Returns default criteria associations to apply to every search
     *
     * @param array<string> $args additional associations to include
     * @return array<string>
     */
    protected function getCriteriaAssociations(array $args = []): array
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

    protected function getUnsubmittedOrders(): OrderCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->getCriteriaFilter());
        $criteria->addAssociations($this->getCriteriaAssociations());

        $retOrders = new OrderCollection();

        try {
            $orders = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities();

            /** @var OrderEntity $order */
            foreach ($orders as $order) {
                if ($this->isOrderTransferable($order)) {
                    $retOrders->add($order);
                }
            }
        } catch (Exception $e) {
            $this->log('critical', `Exception when searching for unsynchronized orders: {$e->getMessage()}`);
        }

        return $retOrders;
    }

    protected function announceParcel(string $parcelId): void
    {
        try {
            $this->companyApi->updateParcelById($parcelId, new ParcelStatus(['status' => Parcel::STATUS_ANNOUNCED]));
            $this->log('info', 'Parcel number ' . $parcelId . ' changed status to "' . Parcel::STATUS_ANNOUNCED . '"');
        } catch (ApiException $e) {
            $this->log('critical', 'Unable to change parcel status to "' . Parcel::STATUS_ANNOUNCED . '" : ' . $e->getMessage());
        }
    }

    protected function getOrderById(string $orderId): OrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter($this->getCriteriaFilter([new EqualsFilter('id', $orderId)]));
        $criteria->addAssociations($this->getCriteriaAssociations());

        return $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }

    protected function populateEriveDeliveryParcel(OrderEntity $order, bool $announceOnShip): Parcel
    {
        $shippingAddress = $order->getDeliveries()->first()->getShippingOrderAddress();
        $countPackagingUnits = $this->systemConfigService->get('EriveDelivery.config.countPackagingUnits', $order->getSalesChannelId()) ?? false;

        $parcelWidth = 0;
        $parcelLength = 0;
        $parcelHeight = 0;
        $parcelWeight = 0;
        $totalPackagingUnits = 0;

        /** @var OrderLineItemEntity $item */
        foreach ($order->getLineItems()->getElements() as $item) {
            if ($item->getProduct()) {
                $quantity = intval($item->getQuantity() ?: 1);
                $prodWeight = floatval($item->getProduct()->getWeight() ?: 0);
                $prodWidth = floatval($item->getProduct()->getWidth() ?: 0);
                $prodLength = floatval($item->getProduct()->getLength() ?: 0);
                $prodHeight = floatval($item->getProduct()->getHeight() ?: 0);

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

        try {
            $parcel = new Parcel();
            $parcel->setExternalReference($order->getOrderNumber() ?? '-');
            $parcel->setComment($order->getCustomerComment() ?? '');
            $parcel->setWeight($parcelWeight);
            $parcel->setWidth($parcelWidth);
            $parcel->setLength($parcelLength);
            $parcel->setHeight($parcelHeight);
            $parcel->setPackagingUnits($countPackagingUnits ? $totalPackagingUnits : 1);

            $orderDeliveryStatus = $order->getDeliveries()->first()->getStateMachineState()->getTechnicalName();
            if ($announceOnShip && ($orderDeliveryStatus === 'shipped')) {
                $parcel->setStatus(Parcel::STATUS_ANNOUNCED);
            }
        } catch (\Throwable $e) {
            throw new \Exception('Unable to create a parcel: ' . $e->getMessage());
        }

        try {
            $customer = new Customer();
            $customer->setName(implode(' ', array_filter([$order->getOrderCustomer()->getFirstName(), $order->getOrderCustomer()->getLastName()])) ?: '-');
            $customer->setEmail($order->getOrderCustomer()->getEmail() ?: '-');
            $customer->setPhone($shippingAddress->getPhoneNumber() ?: '0');
        } catch (\Throwable $e) {
            throw new \Exception('Unable to create customer: ' . $e->getMessage());
        }

        try {
            $customerAddress = new Address();
            $country = $shippingAddress->getCountry()->getIso();
            $allowedValues = $customerAddress->getCountryAllowableValues();
            if (!in_array($country, $allowedValues, true)) {
                throw new \Exception(
                    sprintf(
                        "Invalid value '%s' for 'country', must be one of '%s'",
                        $country,
                        implode("', '", $allowedValues)
                    )
                );
            } else {
                $customerAddress->setCountry($country);
            }
            $customerAddress->setCity($shippingAddress->getCity() ?? '-');
            $customerAddress->setZip($shippingAddress->getZipcode() ?? '-');
            $customerAddress->setStreet($shippingAddress->getStreet() ?? '-');
            $customerAddress->setStreetNumber(preg_replace('/^.*?(?=\d)/', '', $shippingAddress->getStreet()));
            if (!empty($shippingAddress->getAdditionalAddressLine1())) {
                $customerAddress->setComment((empty($customerAddress->getComment()) ? '' : $customerAddress->getComment() . ', ') . $shippingAddress->getAdditionalAddressLine1());
            }
            if (!empty($shippingAddress->getAdditionalAddressLine2())) {
                $customerAddress->setComment((empty($customerAddress->getComment()) ? '' : $customerAddress->getComment() . ', ') . $shippingAddress->getAdditionalAddressLine2());
            }
        } catch (\Throwable $e) {
            throw new \Exception('Unable to create customer address: ' . $e->getMessage());
        }

        if (!$customerAddress->valid()) {
            throw new \Exception('Unable to create customer address');
        }

        if (!$customer->valid()) {
            throw new \Exception('Unable to create customer');
        }

        try {
            $customer->setAddress($customerAddress);
            $parcel->setTo($customer);
        } catch (\Throwable $e) {
            throw new \Exception('Unable to populate parcel data: ' . $e->getMessage());
        }

        return $parcel;
    }

    protected function processOrderWithParcelData(OrderEntity $order): void
    {
        $trackingNumbers = $this->readTrackingNumbers($order->getId());
        $announceOnShip = $this->systemConfigService->get('EriveDelivery.config.announceParcelOnShip', $order->getSalesChannelId()) ?? false;

        /** @var string $trackingNumber */
        foreach ($trackingNumbers as $trackingNumber) {
            try {
                $pubParcel = $trackingNumber ? $this->companyApi->getParcelById($trackingNumber) : null;
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
            } catch (\Throwable $e) {
                $this->log('critical', 'Client Error! ' . $e->getMessage());
                return;
            }

            if ($pubParcel) {
                $parcelId = $trackingNumber;
                break;
            }
        }

        if (!isset($pubParcel)) {
            try {
                $preparedParcel = $this->populateEriveDeliveryParcel($order, $announceOnShip);
            } catch (\Throwable $e) {
                $this->log('critical', 'Unable to create parcel: ' . $e->getMessage());
                return;
            }

            try {
                $pubParcel = $this->companyApi->submitParcel($preparedParcel)->getParcel();
            } catch(\Throwable $e) {
                $this->log('critical', sprintf('Exception when processing order number %s: %s', $order->getOrderNumber(), $e->getMessage()));
                return;
            }

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

        $deliveries = $order->getDeliveries();
        if ($deliveries->count() === 0) {
            return;
        }

        $delivery = $deliveries->first();
        $deliveryState = $delivery->getStateMachineState()->getTechnicalName();

        if (
            isset($parcelId) &&
            $announceOnShip &&
            $pubParcel->getStatus() === Parcel::STATUS_PREPARED_BY_SENDER &&
            $deliveryState === 'shipped'
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
        if (!$this->isOrderTransferable($order)) {
            return;
        }

        $this->log('info', 'Processing order # ' . $order->getOrderNumber() . ' (id: ' . $order->getId() . ')');

        $eriveEnv = $this->systemConfigService->get('EriveDelivery.config.eriveEnvironment', $order->getSalesChannelId()) ?? 'www';
        $apiKey = $this->systemConfigService->get('EriveDelivery.config.apiKey', $order->getSalesChannelId()) ?? '';
        $apiTestKey = $this->systemConfigService->get('EriveDelivery.config.apiTestKey', $order->getSalesChannelId()) ?? '';
        $customApiEndpoint = $this->systemConfigService->get('EriveDelivery.config.customApiEndpoint', $order->getSalesChannelId()) ?? '';

        switch ($eriveEnv) {
            case 'www':
                $this->config->setHost('https://' . $eriveEnv . '.erive.delivery/api/v1');
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

        if (!$this->isApiKeySet($order->getSalesChannelId())) {
            return;
        }

        $this->companyApi = new CompanyApi(new Client(), $this->config);

        $this->processOrderWithParcelData($order);
    }

    protected function isReturnOrder(OrderEntity $order): bool
    {
        $customFields = $order->getCustomFields();
        return is_array($customFields) && array_key_exists('isReturnOrder', $customFields) && $customFields['isReturnOrder'];
    }

    protected function isOrderTransferable(OrderEntity $order): bool
    {
        $allowedDeliveryMethodIds = $this->systemConfigService->get('EriveDelivery.config.deliveryMethods', $order->getSalesChannelId()) ?? [];
        /** @var string $orderDeliveryMethodId */
        foreach ($order->getDeliveries()->getShippingMethodIds() as $orderDeliveryMethodId) {
            if (in_array($orderDeliveryMethodId, $allowedDeliveryMethodIds)) {
                return true;
            }
        }
        return false;
    }

    protected function log(string $level, string $msg): void
    {
        if (\method_exists($this->logger, $level)) {
            $this->logger->$level('ERIVE.delivery: ' . $msg);
        } else {
            $this->logger->notice('ERIVE.delivery (' . $level . '): ' . $msg);
        }
    }
}
