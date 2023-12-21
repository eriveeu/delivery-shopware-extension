<?php

declare(strict_types=1);

namespace Erive\Delivery\Listener;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Erive\Delivery\ScheduledTask\OrderSubmission\OrderSubmissionTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

class ConfigListener
{
    protected const CONFIG_ENABLE_SCHEDULED_TASK = 'EriveDelivery.config.enableScheduledTask';
    // protected const CONFIG_SCHEDULED_TASK_INTERVAL = 'EriveDelivery.config.scheduledTaskInterval';

    protected SystemConfigService $systemConfigService;
    protected EntityRepositoryInterface $scheduledTaskRepository;

    protected array $configKeys = [
        self::CONFIG_ENABLE_SCHEDULED_TASK, 
        // self::CONFIG_SCHEDULED_TASK_INTERVAL
    ];

    protected EntityRepositoryInterface $repository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $scheduledTaskRepository
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
                ($event->getValue() ? 'scheduled' : 'skipped') :
                ($this->systemConfigService->get(self::CONFIG_ENABLE_SCHEDULED_TASK) ? 'scheduled' : 'skipped'),
            // 'run_interval' => $event->getKey() === self::CONFIG_SCHEDULED_TASK_INTERVAL ? 
            //     $event->getValue() : 
            //     ($this->systemConfigService->get(self::CONFIG_SCHEDULED_TASK_INTERVAL) ?? OrderSubmissionTask::getDefaultInterval())
        ];
        $this->scheduledTaskRepository->upsert([$upsertCommand], $context);
    }
}
