<?php

namespace Dtc\QueueBundle\Tests\Redis;

use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Redis\JobManager;
use Dtc\QueueBundle\Redis\Predis;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Manager\AutoRetryTrait;
use Dtc\QueueBundle\Tests\Manager\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\Manager\PriorityTestTrait;
use Dtc\QueueBundle\Tests\Manager\RetryableTrait;
use Dtc\QueueBundle\Tests\Manager\SaveJobTrait;
use Dtc\QueueBundle\Util\Util;
use Predis\Client;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class JobManagerTest extends BaseJobManagerTest
{
    use PriorityTestTrait;
    use AutoRetryTrait;
    use RetryableTrait;
    use SaveJobTrait;
    public static $connection;

    public static function setUpBeforeClass()
    {
        if (self::$jobManager) {
            parent::setUpBeforeClass();

            return;
        }

        $host = getenv('REDIS_HOST');
        $port = getenv('REDIS_PORT') ?: 6379;
        $jobTimingClass = JobTiming::class;
        $runClass = Run::class;
        $predisClient = new Client(['scheme' => 'tcp', 'host' => $host, 'port' => $port]);
        $predisClient->flushall();
        $predis = new Predis($predisClient);

        self::$jobTimingManager = new JobTimingManager($jobTimingClass, false);
        self::$runManager = new RunManager($runClass);
        self::$jobManager = new JobManager(self::$runManager, self::$jobTimingManager, \Dtc\QueueBundle\Redis\Job::class, 'test_cache_key');
        self::$jobManager->setRedis($predis);
        self::$jobManager->setMaxPriority(255);
        self::$worker = new FibonacciWorker();
        parent::setUpBeforeClass();
    }

    public function testConstructor()
    {
        $test = null;
        try {
            $test = new JobManager(self::$runManager, self::$jobTimingManager, Job::class, 'something');
        } catch (\Exception $exception) {
            self::fail("shouldn't get here");
        }
        self::assertNotNull($test);
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

    public function testExpiredJob()
    {
        $this->drain();
        $job = new self::$jobClass(self::$worker, false, null);
        $time = time() - 1;
        $job->setExpiresAt(new \DateTime("@$time"))->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob();
        self::assertNull($jobInQueue, 'The job should have been dropped...');

        $job = new self::$jobClass(self::$worker, false, null);
        $time = time() - 1;
        $job->setExpiresAt(new \DateTime("@$time"))->fibonacci(1);

        $job = new self::$jobClass(self::$worker, false, null);
        $time = time() - 1;
        $job->setExpiresAt(new \DateTime("@$time"))->fibonacci(5);

        $job = new self::$jobClass(self::$worker, false, null);
        $time = time() - 1;
        $job->setExpiresAt(new \DateTime("@$time"))->fibonacci(2);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
    }

    public function testInvalidReset()
    {
        $job = new \Dtc\QueueBundle\RabbitMQ\Job();
        $failure = false;
        try {
            self::$jobManager->resetJob($job);
            $failure = true;
        } catch (\InvalidArgumentException $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failure);
    }

    public function testBatchJobs()
    {
        $this->drain();

        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $worker = self::$worker;
        $job1 = $worker->later()->fibonacci(1);
        $job2 = $worker->batchLater()->fibonacci(1);

        self::assertEquals($job1->getId(), $job2->getId());

        $job = self::$jobManager->getJob();
        self::assertEquals($job1->getId(), $job->getId());
        self::assertEquals($job1->getPriority(), $job->getPriority());

        $job = self::$jobManager->getJob();
        self::assertNull($job);

        if (null !== self::$jobManager->getMaxPriority()) {
            $job1 = $worker->later()->fibonacci(1);
            $job2 = $worker->batchLater()->setPriority(3)->fibonacci(1);
            self::assertEquals($job1->getId(), $job2->getId());
            self::assertNotEquals($job1->getPriority(), $job2->getPriority());

            $job = self::$jobManager->getJob();
            self::assertNotNull($job);
            self::assertEquals($job1->getId(), $job->getId());
            self::assertEquals($job->getPriority(), $job2->getPriority());

            $job = self::$jobManager->getJob();
            self::assertNull($job);
        }

        $job1 = $worker->later(100)->fibonacci(1);
        $time1 = new \DateTime('@'.time());
        $job2 = $worker->batchLater(0)->fibonacci(1);
        $time2 = Util::getDateTimeFromDecimalFormat(Util::getMicrotimeInteger());

        self::assertEquals($job1->getId(), $job2->getId());
        self::assertGreaterThanOrEqual($time1, $job2->getWhenAt());
        self::assertLessThanOrEqual($time2, $job2->getWhenAt());

        $job = self::$jobManager->getJob();
        self::assertNotNull($job);
        self::assertEquals($job1->getId(), $job->getId());
        if (null !== self::$jobManager->getMaxPriority()) {
            self::assertNotNull($job->getPriority());
        } else {
            self::assertNull($job->getPriority());
        }
        self::assertGreaterThanOrEqual($time1, $job->getWhenAt());
        self::assertLessThanOrEqual($time2, $job->getWhenAt());

        if (null !== self::$jobManager->getMaxPriority()) {
            $job1 = $worker->later(100)->setPriority(3)->fibonacci(1);
            $priority1 = $job1->getPriority();
            $time1 = Util::getDateTimeFromDecimalFormat(Util::getMicrotimeInteger());
            $job2 = $worker->batchLater(0)->setPriority(1)->fibonacci(1);
            $time2 = Util::getDateTimeFromDecimalFormat(Util::getMicrotimeInteger());
            self::assertEquals($job1->getId(), $job2->getId());
            self::assertNotEquals($priority1, $job2->getPriority());

            self::assertGreaterThanOrEqual($time1, $job2->getWhenAt());
            self::assertLessThanOrEqual($time2, $job2->getWhenAt());
        }

        $this->drain();
    }

    public function testGetStatus()
    {
        list(, $status1) = $this->getBaseStatus();
        list(, $status2) = $this->getBaseStatus();
        $fibonacciStatus1 = $status1['fibonacci->fibonacci()'];
        $fibonacciStatus2 = $status2['fibonacci->fibonacci()'];

        self::assertEquals($fibonacciStatus1[BaseJob::STATUS_NEW] + 1, $fibonacciStatus2[BaseJob::STATUS_NEW]);
    }

    protected function getBaseStatus()
    {
        /** @var JobManager|\Dtc\QueueBundle\ORM\JobManager $jobManager */
        $jobManager = self::$jobManager;
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $status = $jobManager->getStatus();
        self::assertArrayHasKey('fibonacci->fibonacci()', $status);
        $fibonacciStatus = $status['fibonacci->fibonacci()'];

        self::assertArrayHasKey(BaseJob::STATUS_NEW, $fibonacciStatus);
        self::assertArrayHasKey(BaseJob::STATUS_EXCEPTION, $fibonacciStatus);
        self::assertArrayHasKey(BaseJob::STATUS_RUNNING, $fibonacciStatus);
        self::assertArrayHasKey(BaseJob::STATUS_SUCCESS, $fibonacciStatus);
        self::assertArrayHasKey(Job::STATUS_EXPIRED, $fibonacciStatus);
        self::assertArrayHasKey(RetryableJob::STATUS_MAX_EXCEPTIONS, $fibonacciStatus);
        self::assertArrayHasKey(RetryableJob::STATUS_MAX_FAILURES, $fibonacciStatus);
        self::assertArrayHasKey(RetryableJob::STATUS_MAX_RETRIES, $fibonacciStatus);

        return [$job, $status];
    }
}
