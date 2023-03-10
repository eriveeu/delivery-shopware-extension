<?php

namespace NewMobilityEnterprise\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;

// use Shopware\Core\System\SystemConfig\SystemConfigService;
// use NewMobilityEnterprise\Service\OrderService;

/**
 * DI Config:
 *
 * <service id="NewMobilityEnterprise\OrderSubmissionTaskHandler">
 * <argument type="service" id="scheduled_task.repository"/>
 * <tag name="messenger.message_handler"/>
 * </service>
 */
class OrderSubmissionTaskHandler extends ScheduledTaskHandler
{
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository,
    )
    {
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
        // (new OrderService($this->systemConfigService, $this->orderRepository))->processAllOrders();
        $this->logger->notice('GreenToHome: Scheduled task ran');
    }
}