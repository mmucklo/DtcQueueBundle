<?php
namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use Dtc\QueueBundle\Model\WorkerManager;

class StaticJobManagerTest
    extends BaseJobManagerTest
{
    public static function setUpBeforeClass() {
        self::$jobManager = new StaticJobManager();
        self::$worker = new FibonacciWorker();
        parent::setUpBeforeClass();
    }
}
