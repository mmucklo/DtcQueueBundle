<?php
namespace Dtc\QueueBundle\EventDispatcher;


class EventDispatcher
{
    private $listeners = array();

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        foreach ($subscriber->getSubscribedEvents() as $key => $value) {
            $this->listeners[$key][] = [$subscriber, $value];
        }
    }

    public function hasListeners($eventName)
    {
        if ( ! isset($this->listeners[$eventName])) {
            return false;
        }

        return $this->listeners[$eventName];
    }

    public function dispatch($eventName, Event $event)
    {
        if ( ! isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $callback) {
            call_user_func($callback, $event);
        }
    }
}
