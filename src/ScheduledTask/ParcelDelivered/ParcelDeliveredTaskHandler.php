<?php

namespace Erive\Delivery\ScheduledTask\ParcelDelivered;

use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ParcelDeliveredTaskHandler extends ScheduledTaskHandler
{
    protected LoggerInterface $logger;
    protected OrderService $orderService;
    
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        OrderService $orderService
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->logger = $logger;
        $this->orderService = $orderService;
    }

    public static function getHandledMessages(): iterable {
        return [ParcelDeliveredTask::class];
    }

    public function run(): void {
        $this->logger->notice('ERIVE.delivery: Updating order status for delivered parcels (TODO)');
        // $this->orderService->processAllOrders();
    }
}
