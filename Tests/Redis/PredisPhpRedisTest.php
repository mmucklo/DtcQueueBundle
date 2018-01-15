<?php

namespace Dtc\QueueBundle\Tests\Redis;

use Dtc\QueueBundle\Redis\RedisInterface;
use Dtc\QueueBundle\Redis\Predis;
use Dtc\QueueBundle\Redis\PhpRedis;
use Predis\Client;

class PredisPhpRedisTest extends \PHPUnit\Framework\TestCase
{
    public function getPhpRedis()
    {
        $host = getenv('REDIS_HOST');
        $port = getenv('REDIS_PORT') ?: 6379;
        $jobTimingClass = JobTiming::class;
        $runClass = Run::class;
        $redis = new \Redis();
        $redis->connect($host, $port);
        $redis->flushAll();
        $phpredis = new PhpRedis($redis);

        return $phpredis;
    }

    public function getPredis()
    {
        $host = getenv('REDIS_HOST');
        $port = getenv('REDIS_PORT') ?: 6379;
        $jobTimingClass = JobTiming::class;
        $runClass = Run::class;
        $predisClient = new Client(['scheme' => 'tcp', 'host' => $host, 'port' => $port]);
        $predisClient->flushall();
        $predis = new Predis($predisClient);

        return $predis;
    }

    public function testPredisRedis()
    {
        $redisConnections = [$this->getPhpRedis(), $this->getPredis()];

        foreach ($redisConnections as $connection) {
            $this->getSet($connection);
            $this->del($connection);
            $this->setEx($connection);
            $this->lPush($connection);
            $this->lRem($connection);
            $this->lRange($connection);
            $this->zAdd($connection);
            $this->zRem($connection);
            $this->zPop($connection);
            $this->zPopByMaxScore($connection);
        }
    }

    public function getSet(RedisInterface $redis)
    {
        $redis->del(['testKey']);
        self::assertFalse($redis->get('testKey'));
        self::assertTrue($redis->set('testKey', 1234));
        self::assertEquals(1234, $redis->get('testKey'));
        self::assertTrue($redis->set('testKey', 12345));
        self::assertEquals(12345, $redis->get('testKey'));
    }

    public function setEx(RedisInterface $redis)
    {
        self::assertTrue($redis->setEx('testKey', 1, 1234));
        self::assertEquals(1234, $redis->get('testKey'));
        sleep(2);
        self::assertEquals(false, $redis->get('testKey'));
    }

    public function lPush(RedisInterface $redis)
    {
        $redis->del(['test_list']);
        $result = $redis->lPush('test_list', ['a']);
        self::assertEquals(1, $result);
        $result = $redis->lPush('test_list', ['b']);
        self::assertEquals(2, $result);
        $result = $redis->lPush('test_list', ['c', 'd']);
        self::assertEquals(4, $result);
    }

    public function lRem(RedisInterface $redis)
    {
        $redis->del(['test_list']);
        $result = $redis->lPush('test_list', ['a']);
        self::assertEquals(1, $result);
        $result = $redis->lPush('test_list', ['b']);
        self::assertEquals(2, $result);
        $result = $redis->lRem('test_list', 1, 'a');
        self::assertEquals(1, $result);
        $result = $redis->lRem('test_list', 1, 'a');
        self::assertEquals(false, $result);
        $result = $redis->lPush('test_list', ['a', 'a', 'a']);
        self::assertEquals(4, $result);
        $result = $redis->lRem('test_list', 1, 'a');
        self::assertEquals(1, $result);
        $result = $redis->lRem('test_list', 3, 'a');
        self::assertEquals(2, $result);
        $result = $redis->lRem('test_list', 3, 'b');
        self::assertEquals(1, $result);
    }

    public function lRange(RedisInterface $redis)
    {
        $redis->del(['test_list']);
        $redis->lPush('test_list', ['a']);
        $result = $redis->lRange('test_list', 0, 1);
        self::assertEquals(['a'], $result);
        $result = $redis->lRange('test_list', 0, 0);
        self::assertEquals(['a'], $result);
        $result = $redis->lRange('test_list', 0, 2);
        self::assertEquals(['a'], $result);
        $redis->lPush('test_list', ['b']);
        $result = $redis->lRange('test_list', 0, 0);
        self::assertEquals(['b'], $result);
        $result = $redis->lRange('test_list', 0, 1);
        self::assertEquals(['b', 'a'], $result);
        $result = $redis->lRange('test_list', 0, -1);
        self::assertEquals(['b', 'a'], $result);
        $result = $redis->lRange('test_list', 0, -2);
        self::assertEquals(['b'], $result);
    }

    public function del(RedisInterface $redis)
    {
        self::assertTrue($redis->set('testKey', 1234));
        self::assertTrue($redis->set('testKey1', 12345));
        $result = $redis->del(['testKey']);
        self::assertEquals(1, $result);
        $result = $redis->del(['testKey']);
        self::assertEquals(0, $result);
        self::assertTrue($redis->set('testKey1', 12345));
        $result = $redis->del(['testKey', 'testKey1']);
        self::assertEquals(1, $result);
        $result = $redis->del(['testKey', 'testKey1']);
        self::assertEquals(0, $result);
        self::assertTrue($redis->set('testKey', 1234));
        self::assertTrue($redis->set('testKey1', 12345));
        $result = $redis->del(['testKey', 'testKey1']);
        self::assertEquals(2, $result);
    }

    public function zAdd(RedisInterface $redis)
    {
        $redis->del(['test_zkey']);
        $result = $redis->zAdd('test_zkey', 1, 'a');
        self::assertEquals(1, $result);
        $result = $redis->zAdd('test_zkey', 2, 'a');
        self::assertEquals(0, $result);
    }

    public function zRem(RedisInterface $redis)
    {
        $redis->del(['test_zkey']);
        $redis->zAdd('test_zkey', 1, 'a');
        $result = $redis->zRem('test_zkey', 'a');
        self::assertEquals(1, $result);
        $result = $redis->zRem('test_zkey', 'a');
        self::assertEquals(0, $result);
        $redis->zAdd('test_zkey', 1, 'a');
        $redis->zAdd('test_zkey', 1, 'b');
        $result = $redis->zRem('test_zkey', 'a');
        self::assertEquals(1, $result);
        $result = $redis->zRem('test_zkey', 'b');
        self::assertEquals(1, $result);
    }

    public function zPop(RedisInterface $redis)
    {
        $redis->del(['test_zkey']);
        $redis->zAdd('test_zkey', 1, 'a');
        $redis->zAdd('test_zkey', 2, 'b');
        $result = $redis->zPop('test_zkey');
        self::assertEquals('a', $result);
        $result = $redis->zPop('test_zkey');
        self::assertEquals('b', $result);
    }

    public function zPopByMaxScore(RedisInterface $redis)
    {
        $redis->del(['test_zkey']);
        $redis->zAdd('test_zkey', 123456789, 'a');
        $redis->zAdd('test_zkey', 212345678, 'b');
        $result = $redis->zPopByMaxScore('test_zkey', 0);
        self::assertEquals(false, $result);
        $result = $redis->zPopByMaxScore('test_zkey', 200000000);
        self::assertEquals('a', $result);
        $result = $redis->zPopByMaxScore('test_zkey', 200000000);
        self::assertEquals(false, $result);
        $result = $redis->zPopByMaxScore('test_zkey', 213345678);
        self::assertEquals('b', $result);
    }
}
