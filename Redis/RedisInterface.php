<?php

namespace Dtc\QueueBundle\Redis;

interface RedisInterface
{
    public function zRem($zkey, $value);

    public function zAdd($zkey, $score, $value);

    public function zPopByMaxScore($key, $max);

    public function set($key, $value);

    public function get($key);

    public function del(array $keys);

    public function setEx($key, $ttl, $value);

    public function lRange($lKey, $start, $stop);

    public function lRem($lKey, $count, $value);

    public function lPush($lKey, array $values);
}
