<?php

namespace Erive\Delivery\ScheduledTask\ParcelDelivered;

use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class ParcelDeliveredTaskHandler extends ScheduledTaskHandler {
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        protected LoggerInterface $logger,
        protected OrderService $orderService
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable {
        return [ParcelDeliveredTask::class];
    }

    public function run(): void {
        $this->logger->notice('ERIVE.delivery: Updating order status for delivered parcels (TODO)');
        // $this->orderService->processAllOrders();
    }
}
