<?php

namespace Erive\Delivery\ScheduledTask\ParcelDelivered;

// use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ParcelDeliveredTaskHandler extends ScheduledTaskHandler {
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    public static function getHandledMessages(): iterable {
        return [ParcelDeliveredTask::class];
    }

    public function run(): void {
        $this->logger->notice('ERIVE.delivery: Updating order status for delivered parcels');
        // (new OrderService($this->systemConfigService, $this->orderRepository))->processAllOrders();
    }
}
