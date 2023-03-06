<?php declare(strict_types=1);

namespace NewMobilityEnterprise\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * DI Config:
 *
 * <service id="NewMobilityEnterprise\OrderSubmissionTask">
 * <tag name="shopware.scheduled.task"/>
 * </service>
 */
class OrderSubmissionTask extends ScheduledTask {
    public static function getTaskName(): string {
        return 'at.greentohome.ordersubmission';
    }

    public static function getDefaultInterval(): int {
        return 300;
    }
}
