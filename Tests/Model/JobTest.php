<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\GetterSetterTrait;
use Dtc\QueueBundle\Tests\StaticJobManager;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    use GetterSetterTrait;

    public function testSetArgs()
    {
        $worker = new FibonacciWorker();
        $job = new Job($worker, false, null);
        $job->setArgs(array(1));
        $job->setArgs(array(1, array(1, 2)));

        $failed = false;
        try {
            $job->setArgs(array($job));
            $failed = true;
        } catch (\Exception $e) {
        }
        self::assertFalse($failed);

        try {
            $job->setArgs(array(1, array($job)));
            $failed = true;
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
    }

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('\Dtc\QueueBundle\Model\Job');
    }

    public function testChainJobCall()
    {
        $jobManager = new StaticJobManager();
        $worker = new FibonacciWorker();
        $worker->setJobManager($jobManager);

        $job = new Job($worker, false, null);
        self::assertNull($job->getId(), 'Job id should be null');

        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        $failed = false;
        try {
            $job->invalidFunctionCall();
            $failed = true;
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
    }
}
