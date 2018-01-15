<?php

namespace Dtc\QueueBundle\Tests\Manager;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;

class StaticJobManagerTest extends BaseJobManagerTest
{
    public static function setUpBeforeClass()
    {
        self::$jobTimingManager = new JobTimingManager(JobTiming::class, false);
        self::$runManager = new RunManager(Run::class);
        self::$jobManager = new StaticJobManager(self::$runManager, self::$jobTimingManager, Job::class);
        self::$worker = new FibonacciWorker();
        parent::setUpBeforeClass();
    }

    public function testGetStatus()
    {
        $status = self::$jobManager->getStatus();
        self::assertNull($status);
    }
}
