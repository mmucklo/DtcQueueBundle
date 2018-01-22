<?php

namespace Dtc\QueueBundle\Redis;

use Dtc\QueueBundle\Exception\ClassNotSubclassException;
use Dtc\QueueBundle\Exception\PriorityException;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Manager\JobIdTrait;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\PriorityJobManager;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Util\Util;

/**
 * For future implementation.
 */
class JobManager extends PriorityJobManager
{
    use JobIdTrait;

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

    protected function transferQueues()
    {
        // Drains from WhenAt queue into Prioirty Queue
        $whenQueue = $this->getWhenQueueCacheKey();
        $priorityQueue = $this->getPriorityQueueCacheKey();
        $microtime = Util::getMicrotimeDecimal();
        while ($jobId = $this->redis->zPopByMaxScore($whenQueue, $microtime)) {
            $jobMessage = $this->redis->get($this->getJobCacheKey($jobId));
            if ($jobMessage) {
                $job = new Job();
                $job->fromMessage($jobMessage);
                $this->redis->zAdd($priorityQueue, $job->getPriority(), $job->getId());
            }
        }
    }

    /**
     * @param Job $job
     *
     * @return Job|null
     */
    protected function batchSave(Job $job)
    {
        $crcHash = $job->getCrcHash();
        $crcCacheKey = $this->getJobCrcHashKey($crcHash);
        $result = $this->redis->lrange($crcCacheKey, 0, 1000);
        if (is_array($result)) {
            foreach ($result as $jobId) {
                $jobCacheKey1 = $this->getJobCacheKey($jobId);
                if (!($foundJobMessage = $this->redis->get($jobCacheKey1))) {
                    $this->redis->lRem($crcCacheKey, 1, $jobCacheKey1);
                    continue;
                }

                /// There is one?
                if ($foundJobMessage) {
                    $foundJob = $this->batchFoundJob($job, $jobCacheKey1, $foundJobMessage);
                    if ($foundJob) {
                        return $foundJob;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param string $foundJobCacheKey
     * @param bool   $foundJobMessage
     */
    protected function batchFoundJob(Job $job, $foundJobCacheKey, $foundJobMessage)
    {
        $when = $job->getWhenUs();
        $crcHash = $job->getCrcHash();
        $crcCacheKey = $this->getJobCrcHashKey($crcHash);

        $foundJob = new Job();
        $foundJob->fromMessage($foundJobMessage);
        $foundWhen = $foundJob->getWhenUs();

        // Fix this using bcmath
        $curtimeU = Util::getMicrotimeDecimal();
        $newFoundWhen = null;
        if (bccomp($foundWhen, $curtimeU) > 0 && bccomp($foundWhen, $when) >= 1) {
            $newFoundWhen = $when;
        }
        $foundPriority = $foundJob->getPriority();
        $newFoundPriority = null;
        if ($foundPriority > $job->getPriority()) {
            $newFoundPriority = $job->getPriority();
        }

        return $this->finishBatchFoundJob($foundJob, $foundJobCacheKey, $crcCacheKey, $newFoundWhen, $newFoundPriority);
    }

    /**
     * @param string   $crcCacheKey
     * @param int|null $newFoundPriority
     */
    protected function finishBatchFoundJob(Job $foundJob, $foundJobCacheKey, $crcCacheKey, $newFoundWhen, $newFoundPriority)
    {
        // Now how do we adjust this job's priority or time?
        $adjust = false;
        if (isset($newFoundWhen)) {
            $foundJob->setWhenUs($newFoundWhen);
            $adjust = true;
        }
        if (isset($newFoundPriority)) {
            $foundJob->setPriority($newFoundPriority);
            $adjust = true;
        }
        if (!$adjust) {
            return $foundJob;
        }

        return $this->addFoundJob($adjust, $foundJob, $foundJobCacheKey, $crcCacheKey);
    }

    /**
     * @param bool $adjust
     */
    protected function addFoundJob($adjust, Job $foundJob, $foundJobCacheKey, $crcCacheKey)
    {
        $whenQueue = $this->getWhenQueueCacheKey();
        $result = $this->adjustJob($adjust, $whenQueue, $foundJob, $foundJobCacheKey, $crcCacheKey, $foundJob->getWhenUs());
        if (null !== $result) {
            return $result;
        }
        if (null === $this->maxPriority) {
            return false;
        }

        $priorityQueue = $this->getPriorityQueueCacheKey();
        $result = $this->adjustJob($adjust, $priorityQueue, $foundJob, $foundJobCacheKey, $crcCacheKey, $foundJob->getPriority());

        return $result ?: false;
    }

    /**
     * @param string $queue
     * @param bool   $adjust
     */
    private function adjustJob($adjust, $queue, Job $foundJob, $foundJobCacheKey, $crcCacheKey, $zScore)
    {
        if ($adjust && $this->redis->zRem($queue, $foundJob->getId()) > 0) {
            if (!$this->insertJob($foundJob)) {
                // Job is expired
                $this->redis->lRem($crcCacheKey, 1, $foundJobCacheKey);

                return false;
            }
            $this->redis->zAdd($queue, $zScore, $foundJob->getId());

            return $foundJob;
        }

        return null;
    }

    /**
     * @param \Dtc\QueueBundle\Model\Job $job
     *
     * @return Job|null
     *
     * @throws ClassNotSubclassException
     * @throws PriorityException
     */
    public function prioritySave(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job instanceof Job) {
            throw new \InvalidArgumentException('$job must be instance of '.Job::class);
        }

        $this->validateSaveable($job);
        $this->setJobId($job);

        // Add to whenAt or priority queue?  /// optimizaiton...
        $whenUs = $job->getWhenUs();
        if (!$whenUs) {
            $whenUs = Util::getMicrotimeDecimal();
            $job->setWhenUs($whenUs);
        }

        if (true === $job->getBatch()) {
            // is there a CRC Hash already for this job
            if ($oldJob = $this->batchSave($job)) {
                return $oldJob;
            }
        }

        return $this->saveJob($job);
    }

    protected function saveJob(Job $job)
    {
        $whenQueue = $this->getWhenQueueCacheKey();
        $crcCacheKey = $this->getJobCrcHashKey($job->getCrcHash());
        // Save Job
        if (!$this->insertJob($job)) {
            // job is expired
            return null;
        }
        $jobId = $job->getId();
        $when = $job->getWhenUs();
        // Add Job to CRC list
        $this->redis->lPush($crcCacheKey, [$jobId]);

        $this->redis->zAdd($whenQueue, $when, $jobId);

        return $job;
    }

    /**
     * @param Job $job
     *
     * @return bool false if the job is already expired, true otherwise
     */
    protected function insertJob(Job $job)
    {
        // Save Job
        $jobCacheKey = $this->getJobCacheKey($job->getId());
        if ($expiresAt = $job->getExpiresAt()) {
            $expiresAtTime = $expiresAt->getTimestamp() - time();
            if ($expiresAtTime <= 0) {
                return false; /// ??? job is already expired
            }
            $this->redis->setEx($jobCacheKey, $expiresAtTime, $job->toMessage());

            return true;
        }
        $this->redis->set($jobCacheKey, $job->toMessage());

        return true;
    }

    /**
     * Returns the prioirty in DESCENDING order, except if maxPrioirty is null, then prioirty is 0.
     *
     * @param int|null $priority
     *
     * @return int
     */
    protected function calculatePriority($priority)
    {
        $priority = parent::calculatePriority($priority);
        if (null === $priority) {
            return null === $this->maxPriority ? 0 : $this->maxPriority;
        }

        if (null === $this->maxPriority) {
            return 0;
        }

        // Redis priority should be in DESC order
        return $this->maxPriority - $priority;
    }

    /**
     * @param \Dtc\QueueBundle\Model\Job $job
     *
     * @throws PriorityException
     * @throws ClassNotSubclassException
     */
    protected function validateSaveable(\Dtc\QueueBundle\Model\Job $job)
    {
        if (null !== $job->getPriority() && null === $this->maxPriority) {
            throw new PriorityException('This queue does not support priorities');
        }

        if (!$job instanceof RetryableJob) {
            throw new ClassNotSubclassException('Job needs to be instance of '.RetryableJob::class);
        }
    }

    /**
     * @param string|null $workerName
     * @param string|null $methodName
     * @param bool        $prioritize
     *
     * @throws UnsupportedException
     */
    protected function verifyGetJobArgs($workerName = null, $methodName = null, $prioritize = true)
    {
        if (null !== $workerName || null !== $methodName || (null !== $this->maxPriority && true !== $prioritize)) {
            throw new UnsupportedException('Unsupported');
        }
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        $jobId = $job->getId();
        $priorityQueue = $this->getPriorityQueueCacheKey();
        $whenQueue = $this->getWhenQueueCacheKey();

        $deleted = false;
        if ($this->redis->zRem($priorityQueue, $jobId)) {
            $deleted = true;
        } elseif ($this->redis->zRem($whenQueue, $jobId)) {
            $deleted = true;
        }

        if ($deleted) {
            $this->redis->del([$this->getJobCacheKey($jobId)]);
            $this->redis->lRem($this->getJobCrcHashKey($job->getCrcHash()), 1, $jobId);
        }
    }

    /**
     * @param string|null $workerName
     * @param string|null $methodName
     * @param bool        $prioritize
     * @param mixed       $runId
     *
     * @throws UnsupportedException
     *
     * @return Job|null
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        // First thing migrate any jobs from When queue to Prioirty queue

        $this->verifyGetJobArgs($workerName, $methodName, $prioritize);
        if (null !== $this->maxPriority) {
            $this->transferQueues();
            $queue = $this->getPriorityQueueCacheKey();
            $jobId = $this->redis->zPop($queue);
        } else {
            $queue = $this->getWhenQueueCacheKey();
            $microtime = Util::getMicrotimeDecimal();
            $jobId = $this->redis->zPopByMaxScore($queue, $microtime);
        }

        if ($jobId) {
            $jobMessage = $this->redis->get($this->getJobCacheKey($jobId));
            $job = new Job();
            $job->fromMessage($jobMessage);
            $crcCacheKey = $this->getJobCrcHashKey($job->getCrcHash());
            $this->redis->lRem($crcCacheKey, 1, $job->getId());
            $this->redis->del([$this->getJobCacheKey($job->getId())]);

            return $job;
        }

        return null;
    }

    public function getWaitingJobCount($workerName = null, $methodName = null)
    {
        $microtime = Util::getMicrotimeDecimal();
        $count = $this->redis->zCount($this->getWhenQueueCacheKey(), 0, $microtime);

        if (null !== $this->maxPriority) {
            $count += $this->redis->zCount($this->getPriorityQueueCacheKey(), '-inf', '+inf');
        }

        return $count;
    }

    public function resetJob(RetryableJob $job)
    {
        if (!$job instanceof Job) {
            throw new \InvalidArgumentException('$job must be instance of '.Job::class);
        }
        $job->setStatus(BaseJob::STATUS_NEW);
        $job->setMessage(null);
        $job->setStartedAt(null);
        $job->setRetries($job->getRetries() + 1);
        $job->setUpdatedAt(Util::getMicrotimeDateTime());
        $this->saveJob($job);

        return true;
    }

    protected function collateStatusResults(array &$results, $cacheKey)
    {
        $cursor = null;
        while ($jobs = $this->redis->zScan($cacheKey, $cursor, '', 100)) {
            $jobs = $this->redis->mget(array_map(function ($item) {
                return $this->getJobCacheKey($item);
            }, array_keys($jobs)));
            foreach ($jobs as $jobMessage) {
                if ($jobMessage) {
                    $job = new Job();
                    $job->fromMessage($jobMessage);
                    $resultHashKey = $job->getWorkerName().'->'.$job->getMethod().'()';
                    if (!isset($results[$resultHashKey][BaseJob::STATUS_NEW])) {
                        $results[$resultHashKey] = static::getAllStatuses();
                    }
                    if (!isset($results[$resultHashKey][BaseJob::STATUS_NEW])) {
                        $results[$resultHashKey][BaseJob::STATUS_NEW] = 0;
                    }
                    ++$results[$resultHashKey][BaseJob::STATUS_NEW];
                }
            }
            if (0 === $cursor) {
                break;
            }
        }

        return $results;
    }

    public function getStatus()
    {
        $whenQueueCacheKey = $this->getWhenQueueCacheKey();
        $priorityQueueCacheKey = $this->getPriorityQueueCacheKey();
        $results = [];
        $this->collateStatusResults($results, $whenQueueCacheKey);
        if (null !== $this->maxPriority) {
            $this->collateStatusResults($results, $priorityQueueCacheKey);
        }

        $cacheKey = $this->getStatusCacheKey();
        $cursor = null;
        while ($hResults = $this->redis->hScan($cacheKey, $cursor, '', 100)) {
            foreach ($hResults as $key => $value) {
                list($workerName, $method, $status) = explode(',', $key);
                $resultHashKey = $workerName.'->'.$method.'()';
                if (!isset($results[$resultHashKey])) {
                    $results[$resultHashKey] = static::getAllStatuses();
                }
                if (!isset($results[$resultHashKey][$status])) {
                    $results[$resultHashKey][$status] = 0;
                }
                $results[$resultHashKey][$status] += $value;
            }
            if (0 === $cursor) {
                break;
            }
        }

        return $results;
    }

    public function retryableSaveHistory(RetryableJob $job, $retry)
    {
        $cacheKey = $this->getStatusCacheKey();
        $hashKey = $job->getWorkerName();
        $hashKey .= ',';
        $hashKey .= $job->getMethod();
        $hashKey .= ',';
        $hashKey .= $job->getStatus();
        $this->redis->hIncrBy($cacheKey, $hashKey, 1);
    }
}
