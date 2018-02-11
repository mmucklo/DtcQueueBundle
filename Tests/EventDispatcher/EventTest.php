<?php

namespace Dtc\QueueBundle\Tests\EventDispatcher;

use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\Model\Job;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testEvent()
    {
        $job = new Job();
        $event = new Event($job);

        self::assertEquals($job, $event->getJob());
        $newJob = new Job();
        $event->setJob($newJob);
        self::assertEquals($newJob, $event->getJob());
        self::assertNotEquals($newJob, $job);
    }
}
