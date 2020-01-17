<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Util\Util;

abstract class RetryableJobManager extends AbstractJobManager
{
    protected $defaultMaxRetries;
    protected $defaultMaxFailures;
    protected $defaultMaxExceptions;

    protected $autoRetryOnFailure;
    protected $autoRetryOnException;

    public static function getAllStatuses()
    {
        $statuses = parent::getAllStatuses();
        $statuses[RetryableJob::STATUS_MAX_RETRIES] = 0;
        $statuses[RetryableJob::STATUS_MAX_FAILURES] = 0;
        $statuses[RetryableJob::STATUS_MAX_EXCEPTIONS] = 0;

        return $statuses;
    }

    abstract protected function retryableSave(RetryableJob $job);

    /**
     * @param bool $retry bool
     *
     * @return
     */
    abstract protected function retryableSaveHistory(RetryableJob $job, $retry);

    public function save(Job $job)
    {
        if (!$job instanceof RetryableJob) {
            throw new \InvalidArgumentException('Job is not instanceof '.RetryableJob::class);
        }

        if (!$job->getId()) {
            $this->setBaseRetryableJobDefaults($job);
        }
        $this->recordTiming($job);
        $job->setUpdatedAt(Util::getMicrotimeDateTime());

        return $this->retryableSave($job);
    }

    /**
     * @return bool true if retry
     */
    public function saveHistory(Job $job)
    {
        if (!$job instanceof RetryableJob) {
            throw new \InvalidArgumentException('job not instance of '.RetryableJob::class);
        }

        switch ($job->getStatus()) {
            case BaseJob::STATUS_FAILURE:
                return $this->retryableSaveHistory($job, $this->updateJobFailure($job));
            case BaseJob::STATUS_EXCEPTION:
                return $this->retryableSaveHistory($job, $this->updateJobException($job));
        }

        return $this->retryableSaveHistory($job, false);
    }

    private function updateJobException(RetryableJob $job)
    {
        return $this->updateJobMax($job, 'Exceptions', RetryableJob::STATUS_MAX_EXCEPTIONS, $this->autoRetryOnException);
    }

    /**
     * @param string $type
     * @param bool   $autoRetry
     */
    protected function updateJobMax(RetryableJob $job, $type, $maxStatus, $autoRetry)
    {
        $setMethod = 'set'.$type;
        $getMethod = 'get'.$type;
        $getMax = 'getMax'.$type;
        $job->$setMethod(intval($job->$getMethod()) + 1);
        if (!$this->updateMaxStatus($job, $maxStatus, $job->$getMax(), $job->$getMethod()) &&
            !$this->updateMaxStatus($job, RetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
            if ($autoRetry) {
                return $this->resetRetryableJob($job);
            }
        }

        return false;
    }

    private function updateJobFailure(RetryableJob $job)
    {
        return $this->updateJobMax($job, 'Failures', RetryableJob::STATUS_MAX_FAILURES, $this->autoRetryOnFailure);
    }

    /**
     * Determine if we've hit a max retry condition.
     *
     * @param string   $status
     * @param int|null $max
     * @param int      $count
     *
     * @return bool
     */
    protected function updateMaxStatus(RetryableJob $job, $status, $max = null, $count = 0)
    {
        if (null !== $max && $count >= $max) {
            $job->setStatus($status);

            return true;
        }

        return false;
    }

    protected function resetRetryableJob(RetryableJob $job)
    {
        if ($this->resetJob($job)) {
            $this->getJobTimingManager()->recordTiming(JobTiming::STATUS_INSERT);

            return true;
        }

        return false;
    }

    /**
     * @return bool true if the job was successfully "reset", i.e. re-queued
     */
    abstract protected function resetJob(RetryableJob $job);

    protected function setBaseRetryableJobDefaults(RetryableJob $job)
    {
        if (null === $job->getMaxExceptions()) {
            $job->setMaxExceptions($this->defaultMaxExceptions);
        }

        if (null === $job->getMaxRetries()) {
            $job->setMaxRetries($this->defaultMaxRetries);
        }

        if (null === $job->getMaxFailures()) {
            $job->setMaxFailures($this->defaultMaxFailures);
        }

        if (null === $job->getCrcHash()) {
            $hashValues = [get_class($job), $job->getMethod(), $job->getWorkerName(), $job->getArgs()];
            $crcHash = hash('sha256', serialize($hashValues));
            $job->setCrcHash($crcHash);
        }
    }

    /**
     * @return int|null
     */
    public function getDefaultMaxRetries()
    {
        return $this->defaultMaxRetries;
    }

    /**
     * @param int|null $defaultMaxRetries
     */
    public function setDefaultMaxRetries($defaultMaxRetries)
    {
        $this->defaultMaxRetries = $defaultMaxRetries;
    }

    /**
     * @return int|null
     */
    public function getDefaultMaxFailures()
    {
        return $this->defaultMaxFailures;
    }

    /**
     * @param int|null $defaultMaxFailures
     */
    public function setDefaultMaxFailures($defaultMaxFailures)
    {
        $this->defaultMaxFailures = $defaultMaxFailures;
    }

    /**
     * @return bool
     */
    public function getAutoRetryOnFailure()
    {
        return $this->autoRetryOnFailure;
    }

    /**
     * @param bool $autoRetryOnFailure
     */
    public function setAutoRetryOnFailure($autoRetryOnFailure)
    {
        $this->autoRetryOnFailure = $autoRetryOnFailure;
    }

    /**
     * @return bool
     */
    public function getAutoRetryOnException()
    {
        return $this->autoRetryOnException;
    }

    /**
     * @param bool $autoRetryOnException
     */
    public function setAutoRetryOnException($autoRetryOnException)
    {
        $this->autoRetryOnException = $autoRetryOnException;
    }

    /**
     * @return int|null
     */
    public function getDefaultMaxExceptions()
    {
        return $this->defaultMaxExceptions;
    }

    /**
     * @param int|null $defaultMaxExceptions
     */
    public function setDefaultMaxExceptions($defaultMaxExceptions)
    {
        $this->defaultMaxExceptions = $defaultMaxExceptions;
    }

    protected function recordTiming(Job $job)
    {
        $status = JobTiming::STATUS_INSERT;
        if ($job->getWhenAt() && $job->getWhenAt() > Util::getMicrotimeDateTime()) {
            $status = JobTiming::STATUS_INSERT_DELAYED;
        }

        $this->getJobTimingManager()->recordTiming($status);
    }
}
