<?php declare(strict_types=1);

namespace NewMobilityEnterprise;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * DI Config:
 *
 * <service id="NewMobilityEnterprise\OrderSubmissionTaskTask">
 * <tag name="shopware.scheduled.task"/>
 * </service>
 */
class OrderSubmissionTaskTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'at.greentohome.ordersubmission';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}
