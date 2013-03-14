<?php
namespace Dtc\QueueBundle\Tests\Model;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use Dtc\QueueBundle\Model\WorkerManager;

class StaticJobManagerTest
    extends BaseJobManagerTest
{
    public function setup() {
        $this->jobManager = new StaticJobManager();
        $this->worker = new FibonacciWorker();
        $this->worker->setJobManager($this->jobManager);
    }
}
