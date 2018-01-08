<?php

namespace Dtc\QueueBundle\Tests\Beanstalkd;

use Dtc\QueueBundle\Beanstalkd\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\GetterSetterTrait;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    use GetterSetterTrait;

    public function testGettersSetters()
    {
        $this->runGetterSetterTests('\Dtc\QueueBundle\Beanstalkd\Job');
    }

    public function testToFromMessage()
    {
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
        self::assertEquals($job->getPriority(), $job2->getPriority());

        $worker = new FibonacciWorker();
        $job = new Job($worker, false, null);
        $job->setArgs([1, 2, 3]);
        $job->setMethod('asdf');
        $job->setPriority(1234);
        $date = \Dtc\QueueBundle\Util\Util::getMicrotimeDateTime();
        $job->setExpiresAt($date);
        $message = $job->toMessage();

        $job2 = new Job();
        $priority2 = $job2->getPriority();
        $job2->fromMessage($message);

        self::assertEquals($job->getMethod(), $job2->getMethod());
        self::assertEquals($job->getWorkerName(), $job2->getWorkerName());
        self::assertEquals($job->getArgs(), $job2->getArgs());
        self::assertEquals($job->getPriority(), $job2->getPriority());
        self::assertEquals($job->getExpiresAt(), $job2->getExpiresAt());
    }
}
