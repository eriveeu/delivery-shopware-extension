<?php

declare(strict_types=1);

namespace Erive\Delivery\ScheduledTask\OrderSubmission;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

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
