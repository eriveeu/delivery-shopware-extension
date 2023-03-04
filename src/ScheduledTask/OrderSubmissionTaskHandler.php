<?php 
declare(strict_types=1);

namespace NewMobilityEnterprise\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
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
class OrderSubmissionTaskHandler extends ScheduledTaskHandler
{
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;
    private $logger;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository,
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
    ) {
        parent::__construct($scheduledTaskRepository);

        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    public static function getHandledMessages(): iterable
    {
        return [OrderSubmissionTask::class];
    }

    public function run(): void
    {
        (new ShopwareToGTH($this->systemConfigService, $this->orderRepository))->processAllOrders();
        $this->logger->notice('NewMobilityEnterprise: Orders parsed');
    }
}
