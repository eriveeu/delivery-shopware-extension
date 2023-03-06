<?php declare(strict_types=1);

namespace NewMobilityEnterprise\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * DI Config:
 *
 * <service id="NewMobilityEnterprise\ScheduledTask\ParcelDeliveredTask">
 * <tag name="shopware.scheduled.task"/>
 * </service>
 */
class ParcelDeliveredTask extends ScheduledTask {
    public static function getTaskName(): string {
        return 'at.greentohome.parceldelivered';
    }

    public static function getDefaultInterval(): int {
        // return 3600; // once per hour
        return 86400; // once per day
    }
}
