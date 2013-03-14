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

    public function setup() {
        $this->jobManager = new StaticJobManager();
        $this->worker = new FibonacciWorker();
        $this->worker->setJobManager($this->jobManager);
    }

    /**
     * Not all managers will support job priority
     */
    public function testJobPriority() {
        $firstJob = new Job($this->worker, new \DateTime(), false, 12);
        $firstJob->fibonacci(1);
        $this->assertNotNull($firstJob->getId(), "Job id should be generated");

        $secondJob = new Job($this->worker, new \DateTime(), false, 1);
        $secondJob->fibonacci(1);
        $this->assertNotNull($secondJob->getId(), "Job id should be generated");

        $thirdJob = new Job($this->worker, new \DateTime(), false, 5);
        $thirdJob->fibonacci(1);
        $this->assertNotNull($thirdJob->getId(), "Job id should be generated");

        $jobInQueue = $this->jobManager->getJob();
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
        $job = new Job($this->worker, new \DateTime(), false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");

        $jobInQueue = $this->jobManager->getJob();
        $this->assertEquals($job->getId(), $jobInQueue->getId(),
                "Job id returned by manager should be the same");
    }

    public function testGetJobByWorker() {
        $job = new Job($this->worker, new \DateTime(), false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");

        $jobInQueue = $this->jobManager->getJob($this->worker->getName());
        $this->assertEquals($job->getId(), $jobInQueue->getId(),
                "Job id returned by manager should be the same");
    }

    public function testDeleteJob() {
        $job = new Job($this->worker, new \DateTime(), false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");

        $this->jobManager->deleteJob($job);

        $nextJob = $this->jobManager->getJob();
        $this->assertNull($nextJob, "Shouldn't be any jobs left in queue");
    }

    public function testSaveJob() {
        $job = new Job($this->worker, new \DateTime(), false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), "Job id should be generated");
    }
}
