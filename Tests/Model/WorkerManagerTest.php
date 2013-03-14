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
    protected $jobManager;
    protected $worker;
    protected $workerManager;

    public function setup() {
        $this->jobManager = new StaticJobManager();
        $this->worker = new FibonacciWorker();
        $this->worker->setJobManager($this->jobManager);
        $this->workerManager = new WorkerManager($this->jobManager);
    }

    public function testAddWorker() {
        $this->workerManager->addWorker($this->worker)
        $worker = $this->workerManager->getWorker($this->worker->getName());
        $this->assertEquals($this->worker, $worker);

        try {
            $this->workerManager->addWorker($this->worker)
            $this->fail("Should not be able to add duplicate worker");
        } catch (\Exception $e) {
        }
    }

    public function testRun() {
        $this->workerManager->addWorker($this->worker)
        // Create a job
        $this->worker->later()->fibonacci(20);

        // run the job
        $job = $this->workerManager->run();

        $this->assertEquals(Job::STATUS_SUCCESS, $job->getStatus(),
                "Worker run should be successful");

    }

    public function testRunJob() {
        $this->workerManager->addWorker($this->worker)
        // Create a job
        $job = $this->worker->later()->fibonacci(20);
        $job = $this->workerManager->runJob($job);

        $this->assertEquals(Job::STATUS_SUCCESS, $job->getStatus(),
                "Worker run should be successful");
    }
}
