<?php

namespace Dtc\QueueBundle\Redis;

use Predis\Client;

class Predis implements RedisInterface {

    protected $predis;
    public function __construct(Client $predis) {
        $this->predis = $predis;
    }

    public function hGet($hkey, $key)
    {
        return $this->predis->hget($hkey, $key);
    }

    public function hSet($hkey, $key, $value)
    {
        return $this->predis->hset($hkey, $key, $value);
    }

    public function zAdd($zkey, $score, $member)
    {
        return $this->predis->zadd($zkey, [$member => $score]);
    }

    public function zRange($key, $start, $stop)
    {
        return $this->predis->zrange($key, $start, $stop);
    }

    public function zScore($zkey, $member)
    {
        return $this->predis->zscore($zkey, $member);
    }

    public function zRem($zkey, $member)
    {
        return $this->predis->zrem($zkey, $member);
    }

    public function exec()
    {
        return $this->predis->exec();
    }

    public function multi()
    {
        return $this->predis->multi();
    }

    public function watch($key)
    {
        return $this->predis->watch($key);
    }

    public function zPop($key)
    {
        $this->predis->watch($key);
        $element = $this->predis->zrange($key, 0,0);
        $this->predis->multi();
        $this->predis->zrem($key, $element);
        $result = $this->predis->exec();
        if ($result !== null) {
            return $element;
        }
        return null;
    }

    public function zPopByMaxScore($key, $max) {
        $this->predis->watch($key);
        $element = $this->predis->zrangebyscore($key, 0,$max, ['limit' => ['offset' => 0, 'count' => 1]]);
        $this->predis->multi();
        $this->predis->zrem($key, $element);
        $result = $this->predis->exec();
        if ($result !== null) {
            return $element;
        }
        return null;
    }
}