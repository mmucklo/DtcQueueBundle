<?php

namespace Dtc\QueueBundle\Tests\RabbitMQ;

use Dtc\QueueBundle\RabbitMQ\JobManager;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class JobManagerTest extends BaseJobManagerTest
{
    public static $connection;

    public static function setUpBeforeClass()
    {
        $className = 'Dtc\QueueBundle\RabbitMQ\Job';

        $host = getenv('RABBIT_MQ_HOST');
        $port = 5672;
        $user = 'guest';
        $pass = 'guest';
        $vhost = '/';
        self::$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);

        self::$jobManager = new JobManager();
        self::$jobManager->setAMQPConnection(self::$connection);
        self::$jobManager->setQueueArgs('dtc_queue', false, true, false, false, 255);
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

    /**
     * RabbitMQ has no ability to delete specific messages off the queue.
     */
    public function testDeleteJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        try {
            self::$jobManager->deleteJob($job);
            $this->fail('expected exception');
        } catch (\Exception $e) {
        }
        self::$jobManager->getJob();
    }

    public function testSaveJob()
    {
        $job = new self::$jobClass(self::$worker, false, null);
        $job->fibonacci(1);
        $this->assertNotNull($job->getId(), 'Job id should be generated');

        $jobInQueue = self::$jobManager->getJob();
        $this->assertNotNull($jobInQueue, 'There should be a job.');
        $this->assertEquals(
            $job->getId(),
            $jobInQueue->getId(),
            'Job id returned by manager should be the same'
        );
    }
}
