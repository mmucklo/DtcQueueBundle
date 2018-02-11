<?php

namespace Dtc\QueueBundle\Tests\RabbitMQ;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\RabbitMQ\JobManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Manager\AutoRetryTrait;
use Dtc\QueueBundle\Tests\Manager\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\Manager\PriorityTestTrait;
use Dtc\QueueBundle\Tests\Manager\RetryableTrait;
use Dtc\QueueBundle\Tests\Manager\SaveJobTrait;
use PhpAmqpLib\Connection\AMQPStreamConnection;

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
        $host = getenv('RABBIT_MQ_HOST');
        $port = 5672;
        $user = 'guest';
        $pass = 'guest';
        $vhost = '/';
        $jobTimingClass = JobTiming::class;
        $runClass = Run::class;
        self::$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);

        self::$jobTimingManager = new JobTimingManager($jobTimingClass, false);
        self::$runManager = new RunManager($runClass);
        self::$jobManager = new JobManager(self::$runManager, self::$jobTimingManager, \Dtc\QueueBundle\RabbitMQ\Job::class);
        self::$jobManager->setAMQPConnection(self::$connection);
        self::$jobManager->setMaxPriority(255);
        self::$jobManager->setQueueArgs('dtc_queue', false, true, false, false);
        self::$jobManager->setExchangeArgs('dtc_queue_exchange', 'direct', false, true, false);
        $channel = self::$connection->channel();
        $channel->queue_delete('dtc_queue');
        $channel->close();
        self::$worker = new FibonacciWorker();
        self::$jobManager->setupChannel();
        $channel = self::$jobManager->getChannel();
        $drained = self::drainQueue($channel);

        if ($drained) {
            echo "\nRabbitMQ - drained $drained prior to start of test";
        }
        parent::setUpBeforeClass();
    }

    public function testConstructor()
    {
        $test = null;
        try {
            $test = new JobManager(self::$runManager, self::$jobTimingManager, Job::class);
        } catch (\Exception $exception) {
            self::fail("shouldn't get here");
        }
        self::assertNotNull($test);
    }

    public function testSetupChannel()
    {
        $jobManager = new JobManager(self::$runManager, self::$jobTimingManager, Job::class);
        $failed = false;
        try {
            $jobManager->setupChannel();
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        try {
            $jobManager->setQueueArgs('dtc_queue', false, true, false, false);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $jobManager->setMaxPriority('asdf');
        try {
            $jobManager->setQueueArgs('dtc_queue', false, true, false, false);
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);

        $jobManager->setMaxPriority(255);
        $jobManager->setQueueArgs('dtc_queue', false, true, false, false);
        try {
            $jobManager->setupChannel();
            $failed = true;
        } catch (\Exception $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failed);
        $jobManager->setExchangeArgs('dtc_queue_exchange', 'direct', false, true, false);
        $jobManager->setAMQPConnection(self::$connection);
        $jobManager->setupChannel();
        self::assertTrue(true);
    }

    /**
     * RabbitMQ has no ability to delete specific messages off the queue.
     */
    public function testDeleteJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        try {
            self::$jobManager->deleteJob($job);
            $this->fail('expected exception');
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
        self::$jobManager->getJob();
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

    protected static function drainQueue($channel)
    {
        $drained = 0;
        do {
            $message = $channel->basic_get('dtc_queue');
            if ($message) {
                $channel->basic_ack($message->delivery_info['delivery_tag']);
                ++$drained;
            }
        } while ($message);

        return $drained;
    }

    public function testGetWaitingJobCount()
    {
        /** @var JobManager $jobManager */
        $jobManager = self::$jobManager;
        self::drainQueue($jobManager->getChannel());

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        self::assertEquals(1, self::getWaitingJobCount(2));

        $failure = false;
        try {
            $jobManager->getWaitingJobCount('fibonacci');
            $failure = true;
        } catch (UnsupportedException $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failure);

        $failure = false;
        try {
            $jobManager->getWaitingJobCount(null, 'fibonacci');
            $failure = true;
        } catch (UnsupportedException $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failure);

        $failure = false;
        try {
            $jobManager->getWaitingJobCount('fibonacci', 'fibonacci');
            $failure = true;
        } catch (UnsupportedException $exception) {
            self::assertTrue(true);
        }
        self::assertFalse($failure);

        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        self::assertNotNull($job->getId(), 'Job id should be generated');

        self::assertEquals(2, self::getWaitingJobCount(2));
    }
}
