<?php

namespace Dtc\QueueBundle\Redis;

interface RedisInterface
{
    public function multi();

    public function watch($key);

    public function zPop($key);

    public function zRange($key, $start, $stop);

    public function zRem($zkey, $member);

    public function exec();

    public function zScore($zkey, $member);

    public function zAdd($zkey, $score, $member);

    public function hSet($hkey, $key, $value);

    public function hGet($hkey, $key);
}
