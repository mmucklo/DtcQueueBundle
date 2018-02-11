<?php

namespace Dtc\QueueBundle\Tests\EventDispatcher;

use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Model\Job;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    public function testEventDispatcher()
    {
        $eventDispatcher = new EventDispatcher();
        $eventSubscriber = new StubEventSubscriber();

        self::assertFalse($eventDispatcher->hasListeners(Event::POST_JOB));
        self::assertFalse($eventDispatcher->hasListeners(Event::PRE_JOB));

        $eventDispatcher->addSubscriber($eventSubscriber);
        self::assertTrue($eventDispatcher->hasListeners(Event::POST_JOB));
        self::assertTrue($eventDispatcher->hasListeners(Event::PRE_JOB));

        $job = new Job();
        $event = new Event($job);

        self::assertEmpty($eventSubscriber->getPostJobCalled());
        self::assertEmpty($eventSubscriber->getPreJobCalled());

        $eventDispatcher->dispatch(Event::PRE_JOB, $event);

        self::assertEmpty($eventSubscriber->getPostJobCalled());
        $preJobCalled = $eventSubscriber->getPreJobCalled();
        self::assertNotEmpty($preJobCalled);
        $dispatchedEvent = $preJobCalled[0];
        self::assertEquals($event, $dispatchedEvent);

        $eventDispatcher->dispatch(Event::POST_JOB, $event);

        $postJobCalled = $eventSubscriber->getPostJobCalled();
        self::assertNotEmpty($postJobCalled);
        $dispatchedEvent = $postJobCalled[0];
        self::assertEquals($event, $dispatchedEvent);
    }
}
