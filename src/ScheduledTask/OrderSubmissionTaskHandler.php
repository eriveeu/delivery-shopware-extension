<?php

namespace NewMobilityEnterprise\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use NewMobilityEnterprise\Service\ShopwareToGTH;

/**
 * DI Config:
 *
 * <service id="NewMobilityEnterprise\OrderSubmissionTaskHandler">
 * <argument type="service" id="scheduled_task.repository"/>
 * <tag name="messenger.message_handler"/>
 * </service>
 */
class OrderSubmissionTaskHandler extends ScheduledTaskHandler {
    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository,
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    public static function getHandledMessages(): iterable {
        return [OrderSubmissionTask::class];
    }

    public function run(): void {
        $this->logger->notice('GreenToHome: Processing new orders and adding to GTH for delivery');
        (new ShopwareToGTH($this->systemConfigService, $this->orderRepository))->processAllOrders();
    }
}
