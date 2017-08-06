<?php

namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use Dtc\QueueBundle\Model\WorkerManager;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;

class WorkerManagerTest extends \PHPUnit_Framework_TestCase
{
    protected $jobManager;
    protected $worker;
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
        $this->assertEquals($this->worker, $worker);

        try {
            $this->workerManager->addWorker($this->worker);
            $this->fail('Should not be able to add duplicate worker');
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

        $this->assertNotNull($job, 'Job object should not be null');
        $this->assertEquals(Job::STATUS_SUCCESS, $job->getStatus(),
                'Worker run should be successful');
    }

    public function testErrorRun()
    {
        $this->workerManager->addWorker($this->worker);
        // Create a job
        $this->worker->later()->exceptionThrown(20);

        // run the job
        $job = $this->workerManager->run();

        $this->assertNotNull($job, 'Job object should not be null');
        $this->assertEquals(Job::STATUS_ERROR, $job->getStatus(),
                'Worker run should be not successful');
        $this->assertNotEmpty($job->getMessage(), 'Error message should not be empty');
    }

    public function testRunJob()
    {
        $this->workerManager->addWorker($this->worker);

        // Create a job
        $job = $this->worker->later()->fibonacciFile(20);
        $job = $this->workerManager->runJob($job);

        $this->assertEquals(Job::STATUS_SUCCESS, $job->getStatus(),
                'Worker run should be successful');

        $this->assertEquals('20: 6765', file_get_contents($this->worker->getFilename()),
                'Result of fibonacciFile() must match');
    }
}
