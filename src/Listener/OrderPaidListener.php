<?php declare(strict_types=1);

namespace Erive\Listener;

use Psr\Log\LoggerInterface;
use Erive\Service\OrderService;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
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
        $this->logger->notice('GreenToHome: Processing order # ' . $id);
        (new OrderService($this->systemConfigService, $this->orderRepository))->processOrderById($id);
    }
}