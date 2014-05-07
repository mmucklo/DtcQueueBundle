<?php
namespace Dtc\QueueBundle\EventDispatcher\Subscriber;

use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClearManagerSubscriber
    implements EventSubscriberInterface
{
    private $container;
    public function __construct(ContainerInterface $container) {
        $this->container;
    }

    public function onPostJob(Event $event)
    {
        ve('on post job...');
    }

    public static function getSubscribedEvents()
    {
        return array(
            Event::POST_JOB => 'onPostJob'
        );
    }
}
