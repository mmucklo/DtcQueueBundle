<?php

namespace Dtc\QueueBundle\Redis;

use Dtc\QueueBundle\Manager\JobIdTrait;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\PriorityJobManager;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Manager\VerifyTrait;

/**
 * For future implementation.
 */
abstract class BaseJobManager extends PriorityJobManager
{
    use JobIdTrait;
    use VerifyTrait;

    /** @var RedisInterface */
    protected $redis;

    /** @var string */
    protected $cacheKeyPrefix;

    protected $hostname;
    protected $pid;

    /**
     * @param string $cacheKeyPrefix
     */
    public function __construct(RunManager $runManager, JobTimingManager $jobTimingManager, $jobClass, $cacheKeyPrefix)
    {
        $this->cacheKeyPrefix = $cacheKeyPrefix;
        $this->hostname = gethostname() ?: '';
        $this->pid = getmypid();

        parent::__construct($runManager, $jobTimingManager, $jobClass);
    }

    public function setRedis(RedisInterface $redis)
    {
        $this->redis = $redis;
    }

    protected function getJobCacheKey($jobId)
    {
        return $this->cacheKeyPrefix.'_job_'.$jobId;
    }

    /**
     * @param string $jobCrc
     */
    protected function getJobCrcHashKey($jobCrc)
    {
        return $this->cacheKeyPrefix.'_job_crc_'.$jobCrc;
    }

    protected function getPriorityQueueCacheKey()
    {
        return $this->cacheKeyPrefix.'_priority';
    }

    protected function getWhenQueueCacheKey()
    {
        return $this->cacheKeyPrefix.'_when';
    }

    protected function getStatusCacheKey()
    {
        return $this->cacheKeyPrefix.'_status';
    }
}
