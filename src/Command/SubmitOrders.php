<?php declare(strict_types=1);

namespace Erive\Delivery\Command;

use Erive\Delivery\Service\OrderService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SubmitOrders extends Command
{
    const SUCCESS = 0;
    private SystemConfigService $systemConfigService;
    private EntityRepository $orderRepository;

    public function __construct(
        SystemConfigService $systemConfigService,
        EntityRepository $orderRepository
    ) {
        parent::__construct();

        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

    // Provides a description, printed out in bin/console
    protected function configure(): void
    {
        $this->setName('erive:submit-orders');
        $this->setDescription('Synchronizes all orders from Shopware to ERIVE.delivery');
    }

    // Actual code executed in the command
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        (new OrderService($this->systemConfigService, $this->orderRepository))->processAllOrders();

        $output->writeln('Execution completed' . PHP_EOL);
        return self::SUCCESS;
    }
}