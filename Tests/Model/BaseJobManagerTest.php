<?php
namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use Dtc\QueueBundle\Model\WorkerManager;

class BaseJobManagerTest
    extends \PHPUnit_Framework_TestCase
{
    protected $jobManager;
    protected $worker;
    protected $jobClass;

    public function setUp() {
        $this->jobClass = $this->worker->getJobClass();
        $this->worker->setJobManager($this->jobManager);
    }

    /**
     * Not all managers will support job priority
     */
    public function testJobPriority() {
        $firstJob = new $this->jobClass($this->worker, false, 12);
        $firstJob->fibonacci(1);
        $this->assertNotNull($firstJob->getId(), "Job id should be generated");

        $secondJob = new $this->jobClass($this->worker, false, 1);
        $secondJob->fibonacci(1);
        $this->assertNotNull($secondJob->getId(), "Job id should be generated");

        $thirdJob = new $this->jobClass($this->worker, false, 5);
        $thirdJob->fibonacci(1);
        $this->assertNotNull($thirdJob->getId(), "Job id should be generated");

        $jobInQueue = $this->jobManager->getJob();
        $this->assertNotNull($jobInQueue, "There should be a job.");
        $this->assertEquals($secondJob->getId(), $jobInQueue->getId(),
                "Second job id should be returned - lower number unload first");

        $jobInQueue = $this->jobManager->getJob();
        $this->assertEquals($thirdJob->getId(), $jobInQueue->getId(),
                "Third job id should be returned - lower number unload first");

        $jobInQueue = $this->jobManager->getJob();
        $this->assertEquals($firstJob->getId(), $jobInQueue->getId(),
                "First job id should be returned - lower number unload first");
    }

    public function testGetJob() {
        $job = new $this->jobClass($this->worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");

        $jobInQueue = $this->jobManager->getJob();
        $this->assertNotNull($jobInQueue, "There should be a job.");
        $this->assertEquals($job->getId(), $jobInQueue->getId(),
                "Job id returned by manager should be the same");
    }

    public function testGetJobByWorker() {
        $job = new $this->jobClass($this->worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");

        $jobInQueue = $this->jobManager->getJob($this->worker->getName());
        $this->assertEquals($job->getId(), $jobInQueue->getId(),
                "Job id returned by manager should be the same");
    }

    public function testDeleteJob() {
        $job = new $this->jobClass($this->worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");

        $this->jobManager->deleteJob($job);

        $nextJob = $this->jobManager->getJob();
        $this->assertNull($nextJob, "Shouldn't be any jobs left in queue");
    }

    public function testSaveJob() {
        $job = new $this->jobClass($this->worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");
    }

    public function testPerformance() {
        $start = microtime(true);
        $jobsTotal = 1000;
        $this->jobManager->enableSorting = false;	// Ignore priority

        for ($i = 0; $i < $jobsTotal; $i++) {
            $this->worker->later()->fibonacci(1);
        }

        $total = microtime(true) - $start;
        echo "\nTotal of {$jobsTotal} jobs enqueued in {$total} seconds\n";


        $start = microtime(true);
        for ($i = 0; $i < $jobsTotal; $i++) {
            $job = $this->jobManager->getJob();
        }
        $total = microtime(true) - $start;
        echo "Total of {$jobsTotal} jobs dequeued in {$total} seconds\n";

        $nextJob = $this->jobManager->getJob();
        $this->assertNull($nextJob, "Shouldn't be any jobs left in queue");
    }
}
