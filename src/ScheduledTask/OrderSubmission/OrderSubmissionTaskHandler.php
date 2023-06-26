<?php

namespace Erive\Delivery\ScheduledTask\OrderSubmission;

use Erive\Delivery\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderSubmissionTaskHandler extends ScheduledTaskHandler
{
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;
    private EntityRepository $orderRepository;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepository $orderRepository
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    public static function getHandledMessages(): iterable
    {
        return [OrderSubmissionTask::class];
    }

    public function run(): void
    {
        (new OrderService($this->systemConfigService, $this->orderRepository))->processAllOrders();
        $this->logger->notice('ERIVE.delivery: Scheduled task ran');
    }
}