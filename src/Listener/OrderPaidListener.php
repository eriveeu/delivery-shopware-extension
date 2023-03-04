<?php declare(strict_types=1);

namespace NewMobilityEnterprise\Listener;

use Psr\Log\LoggerInterface;
use NewMobilityEnterprise\Service\ShopwareToGTH;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class OrderPaidListener
{
  private $logger;
  private SystemConfigService $systemConfigService;

  public function __construct(
    LoggerInterface $logger,
    SystemConfigService $systemConfigService,
    EntityRepositoryInterface $orderRepository,
) {
    $this->logger = $logger;
    $this->systemConfigService = $systemConfigService;
    $this->orderRepository = $orderRepository;
  }
  
  public function onOrderTransactionState(EntityWrittenEvent $event): void
  {
    $id = $event->getIds();
    $this->logger->notice('GreenToHome: Processing order # ' . $id[0]);
    (new ShopwareToGTH($this->systemConfigService, $this->orderRepository))->processOrderById($id[0]);
  }
}
