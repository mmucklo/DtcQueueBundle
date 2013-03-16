<?php
namespace Dtc\QueueBundle\Tests\BeanStalkd;

use Dtc\QueueBundle\RabbitMQ\JobManager;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\WorkerManager;

use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\StaticJobManager;
use Dtc\QueueBundle\Tests\Model\BaseJobManagerTest;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class JobManagerTest
    extends BaseJobManagerTest
{
    public static $connection;
    public static  function setUpBeforeClass() {
        $host = 'localhost';
        $className = 'Dtc\QueueBundle\BeanStalkd\Job';

        $host = 'localhost';
        $port = null;
        $user = 'guest';
        $pass = 'guest';
        $vhost = null;
        self::$connection = new AMQPConnection($host, $port, $user, $pass, $vhost);

        self::$jobManager = new JobManager(self::$connection);
        self::$worker = new FibonacciWorker();
        self::$worker->setJobClass($className);

        parent::setUpBeforeClass();
    }
}
