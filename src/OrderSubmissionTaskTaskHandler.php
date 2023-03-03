<?php declare(strict_types=1);

namespace NewMobilityEnterprise;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Swagger\Client\Configuration;
use Shopware\Core\System\SystemConfig\SystemConfigService;


/**
 * DI Config:
 *
 * <service id="NewMobilityEnterprise\OrderSubmissionTaskTaskHandler">
 * <argument type="service" id="scheduled_task.repository"/>
 * <tag name="messenger.message_handler"/>
 * </service>
 */
class OrderSubmissionTaskTaskHandler extends ScheduledTaskHandler
{
//    private SystemConfigService $systemConfigService;
//
//    public function __construct(SystemConfigService $systemConfigService)
//    {
//        parent::__construct();
//        $this->systemConfigService = $systemConfigService;
//    }

    public static function getHandledMessages(): iterable
    {
        return [OrderSubmissionTaskTask::class];
    }

    public function run(): void
    {
//        $exampleConfig = $this->systemConfigService->get('GreenToHome.config.gthEnvironment');


        file_put_contents('test.txt', "done1");
    }
}
