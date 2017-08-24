<?php

namespace Dtc\QueueBundle\Tests\Beanstalkd;

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
        $host = 'localhost';
        $className = 'Dtc\QueueBundle\Beanstalkd\Job';

        $host = 'localhost';
        $port = null;
        $user = 'guest';
        $pass = 'guest';
        $vhost = null;
        self::$connection = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);

        self::$jobManager = new JobManager();
        self::$jobManager->setAMQPConnection(self::$connection);
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($className);

        parent::setUpBeforeClass();
    }
}
