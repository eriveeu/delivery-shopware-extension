<?php

namespace Erive\Delivery\ScheduledTask\OrderSubmission;

use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class OrderSubmissionTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        protected LoggerInterface $logger,
        protected OrderService $orderService
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public static function getHandledMessages(): iterable
    {
        return [OrderSubmissionTask::class];
    }

    public function run(): void
    {
        $this->orderService->processAllOrders();
        $this->logger->notice('ERIVE.delivery: Scheduled task ran');
    }
}