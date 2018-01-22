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
            $this->zCount($connection);
            $this->mGet($connection);
            $this->hIncrBy($connection);
            $this->hGetAll($connection);
            $this->scan($connection, 'hScan');
            $this->scan($connection, 'zScan');
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

    public function hIncrBy(RedisInterface $redis)
    {
        $key = 'testHKey';
        $redis->del([$key]);
        $hashKey1 = 'testHashKey1';
        $result = $redis->hIncrBy($key, $hashKey1, 1);
        self::assertEquals(1, $result);
        $result = $redis->hIncrBy($key, $hashKey1, 1);
        self::assertEquals(2, $result);
        $result = $redis->hIncrBy($key, $hashKey1, 3);
        self::assertEquals(5, $result);
        $hashKey2 = 'testHashKey2';
        $result = $redis->hIncrBy($key, $hashKey2, 3);
        self::assertEquals(3, $result);
    }

    public function hGetAll(RedisInterface $redis)
    {
        $key = 'testHKey';
        $redis->del([$key]);
        $result = $redis->hGetAll($key);
        self::assertEquals([], $result);

        $hashKey1 = 'testHashKey1';
        $redis->hIncrBy($key, $hashKey1, 1);
        $result = $redis->hGetAll($key);
        self::assertEquals([$hashKey1 => 1], $result);
        $redis->hIncrBy($key, $hashKey1, 1);
        $result = $redis->hGetAll($key);
        self::assertEquals([$hashKey1 => 2], $result);
        $hashKey2 = 'testHashKey2';
        $redis->hIncrBy($key, $hashKey2, 3);
        $result = $redis->hGetAll($key);
        self::assertCount(2, $result);
        self::assertArrayHasKey($hashKey1, $result);
        self::assertArrayHasKey($hashKey2, $result);
        self::assertEquals(2, $result[$hashKey1]);
        self::assertEquals(3, $result[$hashKey2]);
    }

    private function prePopulateScan($redis, $type, $key, $hashKeyStart, array &$hashKeys)
    {
        for ($i = 0; $i < 100; ++$i) {
            $hashKey = $hashKeyStart.$i;
            $hashKeys[$hashKey] = 2 * $i;
            if ('hScan' == $type) {
                $redis->hIncrBy($key, $hashKey, $i);
                $redis->hIncrBy($key, $hashKey, $i);
            }
            if ('zScan' == $type) {
                $redis->zAdd($key, $i * 2, $hashKey);
            }
        }
    }

    public function scan(RedisInterface $redis, $type)
    {
        $key = 'testHash';
        $redis->del([$key]);
        $hashKeyStart = 'hash_key_';
        $hashKeys = [];
        $this->prePopulateScan($redis, $type, $key, $hashKeyStart, $hashKeys);

        $cursor = null;
        while (($results = $redis->$type($key, $cursor))) {
            $this->iterateScanResults($results, $hashKeys);
            if (0 === $cursor) {
                break;
            }
        }
        self::assertEmpty($hashKeys);
    }

    private function iterateScanResults(array $results, array &$hashKeys)
    {
        foreach ($results as $key => $value) {
            if (isset($hashKeys[$key]) && $hashKeys[$key] === intval($value)) {
                unset($hashKeys[$key]);
            } else {
                self::assertFalse(true, "Unknown hash key value: $key -> $value - isset? ".isset($hashKeys[$key]));
            }
        }
    }

    public function mGet(RedisInterface $redis)
    {
        $redis->del(['testKey1', 'testKey2']);
        $expectedResult = [false, false];
        self::assertEquals($expectedResult, $redis->mGet(['testKey1', 'testKey2']));
        self::assertTrue($redis->set('testKey1', 1234));
        self::assertTrue($redis->set('testKey2', 12345));
        $expectedResult = [1234, 12345];
        self::assertEquals($expectedResult, $redis->mGet(['testKey1', 'testKey2']));
        $redis->del(['testKey2']);
        $expectedResult = [1234, false];
        self::assertEquals($expectedResult, $redis->mGet(['testKey1', 'testKey2']));

        $redis->del(['testKey1']);
        $expectedResult = [false, false];
        self::assertEquals($expectedResult, $redis->mGet(['testKey1', 'testKey2']));
        self::assertTrue($redis->set('testKey2', 12345));
        $expectedResult = [false, 12345];
        self::assertEquals($expectedResult, $redis->mGet(['testKey1', 'testKey2']));
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

    public function zCount(RedisInterface $redis)
    {
        $redis->del(['test_zkey']);
        $count = $redis->zCount('test_zkey', '-inf', '+inf');
        self::assertEquals(0, $count);
        $redis->zAdd('test_zkey', 1, 'a');
        $count = $redis->zCount('test_zkey', '-inf', '+inf');
        self::assertEquals(1, $count);
        $count = $redis->zCount('test_zkey', 2, '+inf');
        self::assertEquals(0, $count);
        $redis->zAdd('test_zkey', 2, 'b');
        $count = $redis->zCount('test_zkey', '-inf', '+inf');
        self::assertEquals(2, $count);
        $count = $redis->zCount('test_zkey', 2, '+inf');
        self::assertEquals(1, $count);
        $count = $redis->zCount('test_zkey', 0, 1);
        self::assertEquals(1, $count);
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
