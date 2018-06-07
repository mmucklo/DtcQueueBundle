<?php

namespace Dtc\QueueBundle\Tests\EventDispatcher;

use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventSubscriberInterface;

class StubEventSubscriber implements EventSubscriberInterface
{
    protected $postCreateJobCalled;
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

    public function postCreateJob(Event $event)
    {
        $this->postCreateJobCalled[] = $event;
    }

    public function getPreJobCalled()
    {
        return $this->preJobCalled;
    }

    public function getPostJobCalled()
    {
        return $this->postJobCalled;
    }

    public function getPostCreateJobCalled()
    {
        return $this->postCreateJobCalled;
    }

    public static function getSubscribedEvents()
    {
        return [
            Event::POST_CREATE_JOB => 'postCreateJob',
            Event::PRE_JOB => 'preJob',
            Event::POST_JOB => 'postJob',
        ];
    }
}
