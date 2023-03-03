<?php
namespace NewMobilityEnterprise\Subscriber;

use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Frameworkd\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderModificationSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_WRITTEN_EVENT => 'onOrderModified'
        ];
    }

    public function onOrderModified(EntityWrittenEvent $event)
    {
        // get orderid
        // check if order is paid
        // call OrderSubmissionTask
    }
}