<?php declare(strict_types=1);

namespace NewMobilityEnterprise\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use NewMobilityEnterprise\Service\OrderService;

class OrderSubmissionCommand extends Command
{
    const SUCCESS = 0;
    private SystemConfigService $systemConfigService;
    private EntityRepositoryInterface $orderRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepositoryInterface $orderRepository,
    )
    {
        parent::__construct();

        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    // Provides a description, printed out in bin/console
    protected function configure(): void
    {
        $this->setName('gth:submit-orders')->setDescription('Synchronizes orders from Shopware to GTH System.');
    }

    // Actual code executed in the command
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new OrderService($this->systemConfigService, $this->orderRepository))->processAllOrders();

        $output->writeln('Execution completed' . PHP_EOL);
        return self::SUCCESS;
    }
}