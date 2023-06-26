<?php declare(strict_types=1);

namespace Erive\Delivery\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ParcelDeliveredTask extends ScheduledTask {
    public static function getTaskName(): string {
        return 'at.erive.parceldelivered';
    }

    public static function getDefaultInterval(): int {
        // return 3600; // once per hour
        return 86400; // once per day
    }
}
