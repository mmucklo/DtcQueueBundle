<?php

namespace Dtc\QueueBundle\Tests\EventDispatcher;

use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventSubscriberInterface;

class StubEventSubscriber implements EventSubscriberInterface
{
    protected $preJobCalled;
    protected $postJobCalled;

    public function preJob(Event $event)
    {
        $this->preJobCalled[] = $event;
    }

    public function postJob(Event $event)
    {
        $this->postJobCalled[] = $event;
    }

    public function getPreJobCalled()
    {
        return $this->preJobCalled;
    }

    public function getPostJobCalled()
    {
        return $this->postJobCalled;
    }

    public static function getSubscribedEvents()
    {
        return [
            Event::PRE_JOB => 'preJob',
            Event::POST_JOB => 'postJob',
        ];
    }
}
