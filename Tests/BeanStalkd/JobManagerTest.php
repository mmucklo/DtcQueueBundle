<?php
namespace Dtc\QueueBundle\Tests\BeanStalkd;

use Dtc\QueueBundle\BeanStalkd\JobManager;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\WorkerManager;

use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class JobManagerTest
    extends BaseJobManagerTest
{
    public function setup() {
        $host = 'localhost';
        $beanstalkd = new Pheanstalk_Pheanstalk($host);
        $this->jobManager = new JobManager($beanstalkd);
        $this->worker = new FibonacciWorker();

        parent::setup();
    }
}
