<?php
namespace Dtc\QueueBundle\Test\Model;

use Dtc\QueueBundle\Model\Job;

use Dtc\QueueBundle\Test\FibonacciWorker;
use Dtc\QueueBundle\Test\StaticJobManager;
use Dtc\QueueBundle\Model\WorkerManager;
use Monolog\Logger;

class JobTest
    extends \PHPUnit_Framework_TestCase
{
    public function testSetArgs() {
        $job = new Job();
        $job->setArgs(array(1));
        $job->setArgs(array(1, array(1,2)));

        try {
            $job->setArgs(array($job));
            $this->fail("Invalid job argument passed");
        } catch (\Exception $e) {
        }

        try {
            $job->setArgs(array(1, array($job)));
            $this->fail("Invalid job argument passed");
        } catch (\Exception $e) {
        }
    }
}
