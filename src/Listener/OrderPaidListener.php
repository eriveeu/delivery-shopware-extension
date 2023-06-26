<?php declare(strict_types=1);

namespace Erive\Delivery\Listener;

use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderPaidListener
{
    private $logger;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    public function onOrderTransactionState(OrderStateMachineStateChangeEvent $event): void
    {
        $id = $event->getOrderId();
        $this->logger->notice('ERIVE.delivery: Processing order # ' . $id);
        (new OrderService($this->systemConfigService, $this->orderRepository))->processOrderById($id);
    }
}