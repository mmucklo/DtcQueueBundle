<?php

namespace Dtc\QueueBundle\Tests\Beanstalkd;

use Dtc\QueueBundle\Beanstalkd\JobManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;
use Pheanstalk\Pheanstalk;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class JobManagerTest extends BaseJobManagerTest
{
    public static $beanstalkd;

    public static function setUpBeforeClass()
    {
        $host = 'localhost';
        $className = 'Dtc\QueueBundle\Beanstalkd\Job';

        self::$beanstalkd = new Pheanstalk($host);

        self::$jobManager = new JobManager();
        self::$jobManager->setBeanstalkd(self::$beanstalkd);
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($className);

        parent::setUpBeforeClass();
    }
}
