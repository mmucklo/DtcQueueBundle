<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
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
        $reflection = new \ReflectionClass('\Dtc\QueueBundle\Model\Job');
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $name = $property->getName();
            $getMethod = 'get'.ucfirst($name);
            $setMethod = 'set'.ucfirst($name);
            self::assertTrue($reflection->hasMethod($getMethod), $getMethod);
            self::assertTrue($reflection->hasMethod($setMethod), $setMethod);

            $job = new Job();

            $parameters = $reflection->getMethod($setMethod)->getParameters();
            if ($parameters && count($parameters) == 1) {
                $parameter = $parameters[0];
                if (!$parameter->getClass()) {
                    $someValue = 'somevalue';
                    $job->$setMethod($someValue);
                    self::assertSame($someValue, $job->$getMethod(), "$setMethod, $getMethod");
                }
            }
        }
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
