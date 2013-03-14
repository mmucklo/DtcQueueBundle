<?php
namespace Dtc\QueueBundle\Test\Model;

use Dtc\QueueBundle\Model\Job;

use Dtc\QueueBundle\Test\FibonacciWorker;
use Dtc\QueueBundle\Test\StaticJobManager;
use Dtc\QueueBundle\Model\WorkerManager;
use Monolog\Logger;

class WorkerManagerTest
    extends \PHPUnit_Framework_TestCase
{
    protected $worker;
    protected $jobManager;

    public function setUp() {
        $this->jobManager = new StaticJobManager();
        $this->worker = new FibonacciWorker();
        $this->worker->setJobManager($this->jobManager);
    }

    public function testAt() {
        $time = time() + 2600;
        $job = $this->worker->at($time)->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci");

        // Test at with priority
        $priority = 1024;
        $job = $this->worker->at(0, $priority)->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci", $priority);

        // Test job with object
        try {
            $object = new stdClass();
            $job = $this->worker->at($time)->fibonacci($object);
            $this->fail("Exception should be thrown.");
        } catch (\Exception $e) {
        }
    }

    public function testLater() {
        $time = null;
        $job = $this->worker->later()->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci");

        // Test later with priority
        $priority = 1024;
        $job = $this->worker->later(0, $priority)->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci", $priority);

        // Test job with object
        try {
            $object = new stdClass();
            $job = $this->worker->later($time)->fibonacci($object);
            $this->fail("Exception should be thrown.");
        } catch (\Exception $e) {
        }
    }

    public function testBatchLater() {
        $time = null;
        $job = $this->worker->batchLater()->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci");

        /* // Test later
        $later = 100;
        $time = time() + $later;
        $job = $this->worker->batchLater($later)->fibonacci();
        $this->assertJob($job, $time, "fibonacci"); */

        // Test batchLater with priority
        $priority = 1024;
        $job = $this->worker->batchLater(0, $priority)->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci", $priority);

        // Test job with object
        try {
            $object = new stdClass();
            $job = $this->worker->batchLater($time)->fibonacci($object);
            $this->fail("Exception should be thrown.");
        } catch (\Exception $e) {
        }
    }

    public function testBatchAt() {
        $time = time() + 3600;
        $job = $this->worker->batchAt($time)->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci");

        // Test priority
        $priority = 1024;
        $job = $this->worker->batchAt($time, $priority)->fibonacci(20);
        $this->assertJob($job, $time, "fibonacci", $priority);

        // Test job with object
        try {
            $object = new stdClass();
            $job = $this->worker->batchAt($time)->fibonacci($object);
            $this->fail("Exception should be thrown.");
        } catch (\Exception $e) {
        }
    }

    protected function assertJob(Job $job, $time, $method, $priority) {
        $this->assertNotEmpty($job->getId(), "Job should have an id");

        if ($time) {
            $this->assertEquals($time, $job->getWhen()->getTimestamp(),
                    "Job start time should equals");
        }

        if ($priority) {
            $this->assertNull($priority, $job->getPriority(),
                    "Priority should be the same.");
        }
        else {
            $this->assertNull($job->getPriority(), "Priority should be null");
        }

        $this->assertEquals($this->worker->getName(), $job->setWorkerName(),
                "Worker should be the same");
        $this->assertEquals($method, $job->getMethod(),
                "Worker method should be the same");

        // Make sure param gets saved
        $this->assertContains(20, $job->getArgs());
    }
}
