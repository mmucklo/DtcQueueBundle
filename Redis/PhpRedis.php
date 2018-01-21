<?php

namespace Dtc\QueueBundle\Redis;

class PhpRedis implements RedisInterface
{
    protected $redis;
    protected $maxRetries;

    public function __construct(\Redis $redis, $maxRetries = 5)
    {
        $this->redis = $redis;
        $this->maxRetries = $maxRetries;
    }

    public function mGet(array $keys)
    {
        return $this->redis->mGet($keys);
    }

    public function hScan($key, &$cursor, $pattern = '', $count = 0)
    {
        return $this->redis->hScan($key, $cursor, $pattern, $count);
    }

    public function zScan($key, &$cursor, $pattern = '', $count = 0)
    {
        return $this->redis->zScan($key, $cursor, $pattern, $count);
    }

    public function zCount($key, $min, $max)
    {
        return $this->redis->zCount($key, $min, $max);
    }

    public function zAdd($zkey, $score, $value)
    {
        return $this->redis->zadd($zkey, $score, $value);
    }

    public function set($key, $value)
    {
        return $this->redis->set($key, $value);
    }

    public function get($key)
    {
        return $this->redis->get($key);
    }

    public function hIncrBy($key, $hashKey, $value)
    {
        return $this->redis->hIncrBy($key, $hashKey, $value);
    }

    public function hGetAll($key)
    {
        return $this->redis->hGetAll($key);
    }

    public function setEx($key, $seconds, $value)
    {
        return $this->redis->setex($key, $seconds, $value);
    }

    public function lRem($lKey, $count, $value)
    {
        return $this->redis->lrem($lKey, $value, $count);
    }

    public function lPush($lKey, array $values)
    {
        $args = $values;
        array_unshift($args, $lKey);

        return call_user_func_array([$this->redis, 'lPush'], $args);
    }

    public function lRange($lKey, $start, $stop)
    {
        return $this->redis->lrange($lKey, $start, $stop);
    }

    public function del(array $keys)
    {
        return $this->redis->del($keys);
    }

    public function zRem($zkey, $value)
    {
        return $this->redis->zrem($zkey, $value);
    }

    public function zPop($key)
    {
        $retries = 0;
        do {
            $this->redis->watch($key);
            $elements = $this->redis->zrange($key, 0, 0);
            if (empty($elements)) {
                $this->redis->unwatch();

                return null;
            }
            $result = $this->redis->multi()
                ->zrem($key, $elements[0])
                ->exec();
            if (false !== $result) {
                return $elements[0];
            }
            ++$retries;
        } while ($retries < $this->maxRetries);

        return null;
    }

    public function zPopByMaxScore($key, $max)
    {
        $retries = 0;
        do {
            $this->redis->watch($key);
            $elements = $this->redis->zrangebyscore($key, 0, $max, ['limit' => [0, 1]]);
            if (empty($elements)) {
                $this->redis->unwatch();

                return null;
            }
            $result = $this->redis->multi()
                ->zrem($key, $elements[0])
                ->exec();
            if (false !== $result) {
                return $elements[0];
            }
            ++$retries;
        } while ($retries < $this->maxRetries);

        return null;
    }
}
