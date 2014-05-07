<?php
namespace Dtc\QueueBundle\EventDispatcher;

interface EventSubscriberInterface
{
    /**
     * Returns the events to which this class has subscribed.
     *
     * Return format:
     *     array(
     *         array('the-event-name' => 'eventHandler'),
     *         array(...),
     *     )
     *
     * @return array
     */
    public static function getSubscribedEvents();
}
