<?php declare(strict_types=1);

namespace Erive\Delivery\Listener;

use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderPaidListener
{
    protected $logger;
    protected SystemConfigService $systemConfigService;
    protected OrderService $orderService;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        OrderService $orderService
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderService = $orderService;
    }

    public function onOrderTransactionState(OrderStateMachineStateChangeEvent $event): void
    {
        foreach($event->getOrder()->getDeliveries()->getShippingMethodIds() as $orderShippingMethodId) {
            if (in_array($orderShippingMethodId, $this->systemConfigService->get('EriveDelivery.config.deliveryMethods') ?? [])) {
                $orderId = $event->getOrderId();
                $orderNumber = $event->getOrder()->getOrderNumber();
                $this->logger->notice('ERIVE.delivery: Processing order # ' . $orderNumber . ' (ID: '. $orderId . ')');
                $this->orderService->processOrderById($id);
                break;
            }
        }
    }
}