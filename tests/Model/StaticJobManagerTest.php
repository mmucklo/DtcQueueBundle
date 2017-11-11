<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;

class StaticJobManagerTest extends BaseJobManagerTest
{
    public static function setUpBeforeClass()
    {
        self::$jobManager = new StaticJobManager();
        self::$worker = new FibonacciWorker();
        parent::setUpBeforeClass();
    }

    public function testGetStatus()
    {
        $status = self::$jobManager->getStatus();
        self::assertNull($status);
    }
}
