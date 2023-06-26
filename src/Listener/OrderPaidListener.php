<?php declare(strict_types=1);

namespace Erive\Delivery\Listener;

use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderPaidListener
{
    private $logger;
    private SystemConfigService $systemConfigService;
    private EntityRepository $orderRepository;
    private EntityRepository $orderDeliveryRepository;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepository $orderRepository,
        EntityRepository $orderDeliveryRepository
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    public function onOrderTransactionState(OrderStateMachineStateChangeEvent $event): void
    {
        $id = $event->getOrderId();
        $this->logger->notice('ERIVE.delivery: Processing order # ' . $id);
        (new OrderService($this->systemConfigService, $this->orderRepository, $this->orderDeliveryRepository))->processOrderById($id);
    }
}