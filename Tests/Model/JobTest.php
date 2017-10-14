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

        try {
            $job->setArgs(array($job));
            $this->fail('Invalid job argument passed');
        } catch (\Exception $e) {
        }

        try {
            $job->setArgs(array(1, array($job)));
            $this->fail('Invalid job argument passed');
        } catch (\Exception $e) {
        }
    }

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('\Dtc\QueueBundle\Model\Job');
    }

    public function testToFromMessage() {
        $worker = new FibonacciWorker();
        $job = new Job($worker, false, null);
        $job->setArgs([1, 2, 3]);
        $job->setMethod('asdf');
        $job->setPriority(1234);
        $message = $job->toMessage();

        $job2 = new Job();
        $priority2 = $job2->getPriority();
        $job2->fromMessage($message);

        self::assertEquals($job->getMethod(), $job2->getMethod());
        self::assertEquals($job->getWorkerName(), $job2->getWorkerName());
        self::assertEquals($job->getArgs(), $job2->getArgs());
        self::assertEquals($priority2, $job2->getPriority());

        $worker = new FibonacciWorker();
        $job = new Job($worker, false, null);
        $job->setArgs([1, 2, 3]);
        $job->setMethod('asdf');
        $job->setPriority(1234);
        $date = new \DateTime();
        $job->setExpiresAt($date);
        $message = $job->toMessage();

        $job2 = new Job();
        $priority2 = $job2->getPriority();
        $job2->fromMessage($message);

        self::assertEquals($job->getMethod(), $job2->getMethod());
        self::assertEquals($job->getWorkerName(), $job2->getWorkerName());
        self::assertEquals($job->getArgs(), $job2->getArgs());
        self::assertEquals($priority2, $job2->getPriority());
        self::assertEquals($job->getExpiresAt(), $job2->getExpiresAt());
    }

    public function testChainJobCall()
    {
        $jobManager = new StaticJobManager();
        $worker = new FibonacciWorker();
        $worker->setJobManager($jobManager);

        $job = new Job($worker, false, null);
        $this->assertNull($job->getId(), 'Job id should be null');

        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        try {
            $job->invalidFunctionCall();
            $this->fail('invalid chain, should fail');
        } catch (\Exception $e) {
        }
    }
}
