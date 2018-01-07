<?php

namespace Dtc\QueueBundle\Tests\Redis;

use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Redis\JobManager;
use Dtc\QueueBundle\Redis\PhpRedis;
use Dtc\QueueBundle\Tests\FibonacciWorker;
use Dtc\QueueBundle\Tests\Manager\AutoRetryTrait;
use Dtc\QueueBundle\Tests\Manager\PriorityTestTrait;

/**
 * @author David
 *
 * This test requires local beanstalkd running
 */
class PhpRedisJobManagerTest extends JobManagerTest
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
        $redis = new \Redis();
        $redis->connect($host, $port);
        $redis->flushAll();
        $phpredis = new PhpRedis($redis);

        self::$jobTimingManager = new JobTimingManager($jobTimingClass, false);
        self::$runManager = new RunManager($runClass);
        self::$jobManager = new JobManager(
            self::$runManager,
            self::$jobTimingManager,
            \Dtc\QueueBundle\Redis\Job::class,
            'test_cache_key'
        );
        self::$jobManager->setRedis($phpredis);
        self::$jobManager->setMaxPriority(255);
        self::$worker = new FibonacciWorker();
        parent::setUpBeforeClass();
    }
}
