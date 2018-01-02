<?php

namespace Dtc\QueueBundle\Redis;

use Predis\Client;

class Predis implements RedisInterface
{
    protected $predis;
    protected $maxRetries;

    public function __construct(Client $predis, $maxRetries = 5)
    {
        $this->predis = $predis;
        $this->maxRetries = $maxRetries;
    }

    public function zAdd($zkey, $score, $value)
    {
        return $this->predis->zadd($zkey, [$value => $score]);
    }

    public function set($key, $value)
    {
        return $this->predis->set($key, $value);
    }

    public function get($key)
    {
        return $this->predis->get($key);
    }

    public function setEx($key, $seconds, $value)
    {
        return $this->predis->setex($key, $seconds, $value);
    }

    public function lRem($lKey, $count, $value)
    {
        return $this->predis->lrem($lKey, $count, $value);
    }

    public function lPush($lKey, array $values)
    {
        return $this->predis->lpush($lKey, $values);
    }

    public function lRange($lKey, $start, $stop)
    {
        return $this->predis->lrange($lKey, $start, $stop);
    }

    public function del(array $keys)
    {
        return $this->predis->del($keys);
    }

    public function zRem($zkey, $value)
    {
        return $this->predis->zrem($zkey, $value);
    }

    public function zPop($key)
    {
        $element = null;
        $options = [
            'cas' => true,
            'watch' => $key,
            'retry' => $this->maxRetries,
        ];

        try {
            $this->predis->transaction($options, function ($tx) use ($key, &$element) {
                @list($element) = $tx->zrange($key, 0, 0);

                if (isset($element)) {
                    $tx->multi();
                    $tx->zrem($key, $element);
                }
            });
        } catch (\Exception $exception) {
            return null;
        }

        return $element;
    }

    public function zPopByMaxScore($key, $max)
    {
        $element = null;
        $options = [
            'cas' => true,
            'watch' => $key,
            'retry' => $this->maxRetries,
        ];

        try {
            $this->predis->transaction($options, function ($tx) use ($key, $max, &$element) {
                @list($element) = $tx->zrangebyscore($key, 0, $max, ['LIMIT' => [0, 1]]);

                if (isset($element)) {
                    $tx->multi();
                    $tx->zrem($key, $element);
                }
            });
        } catch (\Exception $exception) {
            return null;
        }

        return $element;
    }
}
