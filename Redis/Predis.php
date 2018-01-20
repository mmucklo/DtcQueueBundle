<?php

namespace Dtc\QueueBundle\Redis;

use Predis\Client;
use Predis\Response\Status;
use Predis\Transaction\MultiExec;

class Predis implements RedisInterface
{
    protected $predis;
    protected $maxRetries;

    public function __construct(Client $predis, $maxRetries = 5)
    {
        $this->predis = $predis;
        $this->maxRetries = $maxRetries;
    }

    public function zCount($key, $min, $max)
    {
        return $this->predis->zcount($key, $min, $max);
    }

    public function zAdd($zkey, $score, $value)
    {
        return $this->predis->zadd($zkey, [$value => $score]);
    }

    public function hGetAll($key)
    {
        return $this->predis->hGetAll($key);
    }

    public function hIncrBy($key, $hashKey, $value)
    {
        return $this->predis->hIncrBy($key, $hashKey, $value);
    }

    public function set($key, $value)
    {
        /** @var Status $result */
        $result = $this->predis->set($key, $value);
        if ('OK' == $result->getPayload()) {
            return true;
        }

        return false;
    }

    public function get($key)
    {
        $this->predis->multi();
        $this->predis->exists($key);
        $this->predis->get($key);
        list($exists, $result) = $this->predis->exec();
        if (!$exists) {
            return false;
        }

        return $result;
    }

    public function setEx($key, $seconds, $value)
    {
        $result = $this->predis->setex($key, $seconds, $value);
        if ('OK' == $result->getPayload()) {
            return true;
        }

        return false;
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
            $this->predis->transaction($options, function (MultiExec $tx) use ($key, &$element) {
                list($element) = $tx->zrange($key, 0, 0);

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
            $this->predis->transaction($options, function (MultiExec $tx) use ($key, $max, &$element) {
                list($element) = $tx->zrangebyscore($key, 0, $max, ['LIMIT' => [0, 1]]);

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
