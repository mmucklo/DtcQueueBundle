<?php

namespace Dtc\QueueBundle\Tests\Beanstalkd;

use Dtc\QueueBundle\Beanstalkd\JobManager;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Manager\AutoRetryTrait;
use Dtc\QueueBundle\Tests\Manager\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\Manager\RetryableTrait;
use Pheanstalk\Connection;
use Pheanstalk\Pheanstalk;
use Pheanstalk\SocketFactory;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class JobManagerTest extends BaseJobManagerTest
{
    use AutoRetryTrait;
    use RetryableTrait;
    public static $beanstalkd;

    public static function setUpBeforeClass()
    {
        $host = getenv('BEANSTALKD_HOST');
        $port = getenv('BEANSTALKD_PORT');
        $className = 'Dtc\QueueBundle\Beanstalkd\Job';
        $jobTimingClass = 'Dtc\QueueBundle\Model\JobTiming';
        $runClass = 'Dtc\QueueBundle\Model\Run';

        self::$beanstalkd = new Pheanstalk(new Connection(new SocketFactory($host, $port)));
        self::$jobTimingManager = new JobTimingManager($jobTimingClass, false);
        self::$runManager = new RunManager($runClass);
        self::$jobManager = new JobManager(self::$runManager, self::$jobTimingManager, $className);
        self::$jobManager->setBeanstalkd(self::$beanstalkd);
        self::$worker = new FibonacciWorker();

        $drained = 0;
        do {
            $beanJob = self::$beanstalkd->reserveWithTimeout(1);
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
