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
        $host = getenv('BEANSTALKD_HOST');
        $className = 'Dtc\QueueBundle\Beanstalkd\Job';

        self::$beanstalkd = new Pheanstalk($host);

        self::$jobManager = new JobManager();
        self::$jobManager->setBeanstalkd(self::$beanstalkd);
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($className);

        $drained = 0;
        do {
            $beanJob = self::$beanstalkd->reserve(1);
            if ($beanJob) {
                self::$beanstalkd->delete($beanJob);
                ++$drained;
            }
        } while ($beanJob);

        if ($drained) {
            echo "\nbeanstalkd: drained $drained prior to test\n";
        }
        parent::setUpBeforeClass();
    }

    public function testGetJobByWorker()
    {
        $failed = false;
        try {
            self::$jobManager->getJob(self::$worker->getName());
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
    }
}
