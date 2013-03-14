<?php
namespace Dtc\QueueBundle\Test\Model;

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
        $this->workerManager = new WorkerManager($this->jobManager);
    }

    public function testRun() {

    }

    public function testGetWorkers() {

    }

    public function testRunJob() {

    }

    public function testAddWorker() {

    }

    /** Testing worker **/
    public function testValidCall() {
        $worker->later()->processFibonacci(2);
    }

    public function testInvalidCall() {
        $job = $worker->later()->foobar(2);
        // Make sure job manager has the job
    }
}
