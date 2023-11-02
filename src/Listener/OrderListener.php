<?php

declare(strict_types=1);

namespace Erive\Delivery\Listener;

use Erive\Delivery\Service\OrderService;
// use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderListener
{
    // protected $logger;
    protected SystemConfigService $systemConfigService;
    protected OrderService $orderService;

    public function __construct(
        // LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        OrderService $orderService
    ) {
        // $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderService = $orderService;
    }

    public function onOrderChangeState(OrderStateMachineStateChangeEvent $event): void
    {
        foreach($event->getOrder()->getDeliveries()->getShippingMethodIds() as $orderShippingMethodId) {
            if (in_array($orderShippingMethodId, $this->systemConfigService->get('EriveDelivery.config.deliveryMethods') ?? [])) {
                $this->orderService->processOrderById($event->getOrderId());
                break;
            }
        }
    }
}