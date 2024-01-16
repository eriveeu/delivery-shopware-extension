<?php

declare(strict_types=1);

namespace Erive\Delivery\Listener;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Erive\Delivery\ScheduledTask\OrderSubmission\OrderSubmissionTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;

class ConfigListener
{
    protected const CONFIG_ENABLE_SCHEDULED_TASK = 'EriveDelivery.config.enableScheduledTask';
    protected const CONFIG_SCHEDULED_TASK_INTERVAL = 'EriveDelivery.config.scheduledTaskInterval';

    protected array $configKeys = [
        self::CONFIG_ENABLE_SCHEDULED_TASK, 
        self::CONFIG_SCHEDULED_TASK_INTERVAL
    ];

    protected SystemConfigService $systemConfigService;
    protected EntityRepository $scheduledTaskRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $scheduledTaskRepository
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function onConfigChange(BeforeSystemConfigChangedEvent $event): void
    {
        if (!in_array($event->getKey(), $this->configKeys)) {
            return;
        }

        if ($event->getSalesChannelId() !== null) {
            $event->setValue(null);
        }
        
        if ($event->getValue() === null) {
            return;
        }

        if ($this->systemConfigService->get($event->getKey()) === $event->getValue()) {
            return;
        }

        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', OrderSubmissionTask::getTaskName()));
        
        $taskId = $this->scheduledTaskRepository->searchIds($criteria, $context)->firstId();
        if (!$taskId) {
            return;
        }

        $upsertCommand = [
            'id' => $taskId,
            'status' => $event->getKey() === self::CONFIG_ENABLE_SCHEDULED_TASK ?
                ($event->getValue() ? ScheduledTaskDefinition::STATUS_SCHEDULED : ScheduledTaskDefinition::STATUS_INACTIVE) :
                ($this->systemConfigService->get(self::CONFIG_ENABLE_SCHEDULED_TASK) ? ScheduledTaskDefinition::STATUS_SCHEDULED : ScheduledTaskDefinition::STATUS_INACTIVE)
        ];

        if ($event->getKey() === self::CONFIG_SCHEDULED_TASK_INTERVAL) {
            $runInterval = $event->getValue();
            if (is_int($runInterval) && $runInterval > 0) {
                $upsertCommand['runInterval'] = $runInterval;
            } else {
                $event->setValue($this->systemConfigService->get(self::CONFIG_SCHEDULED_TASK_INTERVAL));
            }
        }

        $this->scheduledTaskRepository->upsert([$upsertCommand], $context);
    }
}
