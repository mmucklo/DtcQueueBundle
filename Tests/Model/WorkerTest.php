<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;

class WorkerTest extends \PHPUnit_Framework_TestCase
{
    protected $worker;
    protected $jobManager;

    public function setUp()
    {
        $this->jobManager = new StaticJobManager();
        $this->worker = new FibonacciWorker();
        $this->worker->setJobManager($this->jobManager);
    }

    public function testAt()
    {
        $time = time() + 2600;
        $job = $this->worker->at($time)->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci');

        // Test at with priority
        $priority = 1024;
        $job = $this->worker->at($time, false, $priority)->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci', $priority);
        self::assertFalse($job->getBatch(), 'Should not be batching');

        // Test job with object
        try {
            $object = new \stdClass();
            $this->worker->at($time)->fibonacci($object);
            self::fail('Exception should be thrown.');
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
    }

    public function testLater()
    {
        $time = null;
        $job = $this->worker->later()->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci');
        self::assertFalse($job->getBatch(), 'Should not be batching');

        // Test later with priority
        $priority = 1024;
        $job = $this->worker->later(0, $priority)->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci', $priority);

        $this->failureTest($time, 'later');
    }

    public function testBatchLater()
    {
        $time = null;
        $job = $this->worker->batchLater()->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci');
        self::assertTrue($job->getBatch(), 'Should be batching');

        // Test batchLater with priority
        $priority = 1024;
        $job = $this->worker->batchLater(0, $priority)->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci', $priority);

        $this->failureTest($time, 'batchLater');
    }

    protected function failureTest($method, $time)
    {
        // Test job with object
        try {
            $object = new \stdClass();
            $this->worker->$method($time)->fibonacci($object);
            self::fail('Exception should be thrown.');
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
    }

    public function testBatchAt()
    {
        $time = time() + 3600;
        $job = $this->worker->batchAt($time)->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci');
        self::assertTrue($job->getBatch(), 'Should be batching');

        // Test priority
        $priority = 1024;
        $job = $this->worker->batchAt($time, $priority)->fibonacci(20);
        self::assertJob($job, $time, 'fibonacci', $priority);

        $this->failureTest($time, 'batchAt');
    }

    /**
     * @param int|null $time
     * @param string   $method
     * @param int      $priority
     */
    protected function assertJob(Job $job, $time, $method, $priority = null)
    {
        self::assertNotEmpty($job->getId(), 'Job should have an id');

        if (null !== $time && $time > 0) {
            self::assertEquals(
                $time,
                $job->getWhenAt()->getTimestamp(),
                    'Job start time should equals'
            );
        }

        if (null !== $priority) {
            self::assertEquals(
                $priority,
                $job->getPriority(),
                    'Priority should be the same.'
            );
        } else {
            self::assertNull($job->getPriority(), 'Priority should be null');
        }

        self::assertEquals(
            $this->worker->getName(),
            $job->getWorkerName(),
                'Worker should be the same'
        );
        self::assertEquals(
            $method,
            $job->getMethod(),
                'Worker method should be the same'
        );

        // Make sure param gets saved
        self::assertContains(20, $job->getArgs());
    }
}
