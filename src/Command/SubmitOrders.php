<?php declare(strict_types=1);

namespace Erive\Delivery\Command;

use Erive\Delivery\Service\OrderService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SubmitOrders extends Command
{
    const SUCCESS = 0;

    public function __construct(
        protected OrderService $orderService
    ) {
        parent::__construct();
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
        $this->orderService->processAllOrders();

        $output->writeln('Execution completed' . PHP_EOL);
        return self::SUCCESS;
    }
}