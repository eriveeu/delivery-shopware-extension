<?php

declare(strict_types=1);

namespace Erive\Delivery\Command;

use Erive\Delivery\Service\OrderService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SubmitOrders extends Command
{
    public const SUCCESS = 0;
    protected OrderService $orderService;

    public function __construct(
        OrderService $orderService
    ) {
        parent::__construct();

        $this->orderService = $orderService;
    }

    protected function configure(): void
    {
        $this->setName('erive:submit-orders');
        $this->setDescription('Synchronizes all orders from Shopware to ERIVE.delivery');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->orderService->processAllOrders();

        $output->writeln('Execution completed' . PHP_EOL);
        return self::SUCCESS;
    }
}
