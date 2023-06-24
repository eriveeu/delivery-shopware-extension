<?php declare(strict_types=1);

namespace Erive\Delivery\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * DI Config:
 *
 * <service id="Erive\Delivery\ScheduledTask\OrderSubmissionTask">
 * <tag name="shopware.scheduled.task"/>
 * </service>
 */
class OrderSubmissionTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'at.erive.ordersubmission';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}