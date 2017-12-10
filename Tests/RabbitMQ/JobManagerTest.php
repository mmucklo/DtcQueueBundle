<?php

namespace Dtc\QueueBundle\Tests\RabbitMQ;

use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\JobTimingManager;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Model\RunManager;
use Dtc\QueueBundle\RabbitMQ\JobManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;
use Dtc\QueueBundle\Tests\Model\PriorityTestTrait;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class JobManagerTest extends BaseJobManagerTest
{
    use PriorityTestTrait;
    public static $connection;

    public static function setUpBeforeClass()
    {
        $className = 'Dtc\QueueBundle\RabbitMQ\Job';

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
        self::$jobManager = new JobManager(self::$runManager, self::$jobTimingManager, Job::class);
        self::$jobManager->setAMQPConnection(self::$connection);
        self::$jobManager->setMaxPriority(255);
        self::$jobManager->setQueueArgs('dtc_queue', false, true, false, false);
        self::$jobManager->setExchangeArgs('dtc_queue_exchange', 'direct', false, true, false);
        $channel = self::$connection->channel();
        $channel->queue_delete('dtc_queue');
        $channel->close();
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($className);
        self::$jobManager->setupChannel();
        $channel = self::$jobManager->getChannel();
        $drained = 0;
        do {
            $message = $channel->basic_get('dtc_queue');
            if ($message) {
                $channel->basic_ack($message->delivery_info['delivery_tag']);
                ++$drained;
            }
        } while ($message);

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
