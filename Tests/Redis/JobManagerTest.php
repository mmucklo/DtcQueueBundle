<?php

namespace Dtc\QueueBundle\Tests\Redis;

use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Redis\JobManager;
use Dtc\QueueBundle\Redis\Predis;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Manager\AutoRetryTrait;
use Dtc\QueueBundle\Tests\Manager\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\Manager\PriorityTestTrait;
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
    public static $connection;

    public static function setUpBeforeClass()
    {
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

    public function testSaveJob()
    {
        // Make sure a job proper type
        $failed = false;
        try {
            $job = new Job();
            self::$jobManager->save($job);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $job = new self::$jobClass(self::$worker, false, null);
        try {
            $job->setPriority(256)->fibonacci(1);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->setPriority(100)->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::$jobManager->saveHistory($jobInQueue);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        $failed = false;
        try {
            self::$jobManager->getJob('fibonacci');
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $failed = false;
        try {
            self::$jobManager->getJob(null, 'fibonacci');
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $jobInQueue = self::$jobManager->getJob();
        self::assertNotNull($jobInQueue, 'There should be a job.');
        self::assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );

        $job->setStatus(BaseJob::STATUS_SUCCESS);
        $badJob = new Job();
        $failed = false;
        try {
            self::$jobManager->saveHistory($badJob);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
        self::$jobManager->saveHistory($jobInQueue);
    }
}
