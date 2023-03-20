<?php declare(strict_types=1);

namespace NewMobilityEnterprise\Subscriber;

use NewMobilityEnterprise\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderSubscriber implements EventSubscriberInterface
{
  private EntityRepository $productRepository;

  public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
        EntityRepository $orderRepository,
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
        $this->orderRepository = $orderRepository;
    }

  public static function getSubscribedEvents(): array
  {
    return [
      'state_enter.order_transaction.state.paid' => 'onOrderPaid',
      ProductEvents::PRODUCT_LOADED_EVENT => 'onProductLoaded'
    ];
  }

  public function onOrderPaid(OrderStateMachineStateChangeEvent $event): void {
    $id = $event->getOrderId();
    $this->logger->notice('GreenToHome: Processing order # ' . $id);
    (new OrderService($this->systemConfigService, $this->orderRepository))->processOrderById($id);
  }

  public function onProductLoaded(EntityLoadedEvent $event): void
  {
    // $this->logger->notice('GreenToHome: Product loaded: ' . print_r($event->getEntities(), true));
  }
}