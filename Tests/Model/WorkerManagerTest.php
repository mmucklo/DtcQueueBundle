<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use PHPUnit\Framework\TestCase;

class WorkerManagerTest extends TestCase
{
    protected $jobManager;
    protected $worker;

    /** @var WorkerManager */
    protected $workerManager;
    protected $eventDispatcher;

    public function setup()
    {
        $this->jobManager = new StaticJobManager();
        $this->worker = new FibonacciWorker();
        $this->worker->setJobManager($this->jobManager);
        $this->eventDispatcher = new EventDispatcher();
        $this->workerManager = new WorkerManager($this->jobManager, $this->eventDispatcher);
    }

    public function testAddWorker()
    {
        $this->workerManager->addWorker($this->worker);
        $worker = $this->workerManager->getWorker($this->worker->getName());
        self::assertEquals($this->worker, $worker);

        try {
            $this->workerManager->addWorker($this->worker);
            self::fail('Should not be able to add duplicate worker');
        } catch (\Exception $e) {
        }
    }

    public function testRun()
    {
        $this->workerManager->addWorker($this->worker);
        // Create a job
        $this->worker->later()->fibonacci(20);

        // run the job
        $job = $this->workerManager->run();

        self::assertNotNull($job, 'Job object should not be null');
        self::assertEquals(
            BaseJob::STATUS_SUCCESS,
            $job->getStatus(),
                'Worker run should be successful'
        );
    }

    public function testErrorRun()
    {
        $this->workerManager->addWorker($this->worker);
        // Create a job
        $this->worker->later()->exceptionThrown(20);

        // run the job
        $job = $this->workerManager->run();

        self::assertNotNull($job, 'Job object should not be null');
        self::assertEquals(
            BaseJob::STATUS_ERROR,
            $job->getStatus(),
                'Worker run should be not successful'
        );
        self::assertNotEmpty($job->getMessage(), 'Error message should not be empty');
    }

    public function testRunJob()
    {
        $this->workerManager->addWorker($this->worker);

        // Create a job
        $job = $this->worker->later()->fibonacciFile(20);
        $job = $this->workerManager->runJob($job);

        self::assertEquals(
            BaseJob::STATUS_SUCCESS,
            $job->getStatus(),
                'Worker run should be successful'
        );

        self::assertEquals(
            '20: 6765',
            file_get_contents($this->worker->getFilename()),
                'Result of fibonacciFile() must match'
        );
    }
}
