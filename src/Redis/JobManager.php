<?php

namespace Dtc\QueueBundle\Redis;

use Dtc\QueueBundle\Model\PriorityJobManager;

/**
 * For future implementation.
 */
abstract class JobManager extends PriorityJobManager
{
    abstract public function setRedis(RedisInterface $redis);
}
