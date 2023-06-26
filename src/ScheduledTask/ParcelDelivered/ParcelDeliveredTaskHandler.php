<?php

namespace Erive\Delivery\ScheduledTask\ParcelDelivered;

// use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ParcelDeliveredTaskHandler extends ScheduledTaskHandler {
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;
    private EntityRepository $orderRepository;
    private EntityRepository $orderDeliveryRepository;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepository $orderRepository,
        EntityRepository $orderDeliveryRepository
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
    }

    public static function getHandledMessages(): iterable {
        return [ParcelDeliveredTask::class];
    }

    public function run(): void {
        $this->logger->notice('ERIVE.delivery: Updating order status for delivered parcels (TODO)');
        // (new OrderService($this->systemConfigService, $this->orderRepository, $this->orderDeliveryRepository))->processAllOrders();
    }
}
