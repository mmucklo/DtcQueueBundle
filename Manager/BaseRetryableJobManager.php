<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\BaseRetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\StallableJob;

abstract class BaseRetryableJobManager extends AbstractJobManager {

    protected $defaultMaxRetries;
    protected $defaultMaxFailures;
    protected $defaultMaxExceptions;

    protected $autoRetryOnFailure;
    protected $autoRetryOnException;

    abstract protected function retryableSave(BaseRetryableJob $job);

    /**
     * @param BaseRetryableJob $job
     * @param $retry bool
     * @return
     */
    abstract protected function retryableSaveHistory(BaseRetryableJob $job, $retry);

    public function save(Job $job) {
        if (!$job instanceof BaseRetryableJob) {
            throw new \InvalidArgumentException("Job is not instanceof " . BaseRetryableJob::class);
        }

        if (!$job->getId()) {
            $this->setBaseRetryableJobDefaults($job);
        }
        $this->recordTiming($job);

        return $this->retryableSave($job);
    }

    /**
     * @return bool true if retry
     * @param bool $retry true if the job was retried, false if not
     */

    public function saveHistory(Job $job) {
        if (!$job instanceof BaseRetryableJob) {
            throw new \InvalidArgumentException("job not instance of " . BaseRetryableJob::class);
        }

        switch ($job->getStatus()) {
            case StallableJob::STATUS_FAILURE:
                return $this->retryableSaveHistory($job, $this->updateJobFailure($job));
            case BaseJob::STATUS_EXCEPTION:
                return $this->retryableSaveHistory($job, $this->updateJobException($job));
        }

        return $this->retryableSaveHistory($job, false);
    }

    protected function updateJobException(BaseRetryableJob $job) {
        $job->setExceptions($job->getExceptions() + 1);
        if (!$this->updateMaxStatus($job, StallableJob::STATUS_MAX_EXCEPTIONS, $job->getMaxExceptions(), $job->getExceptions()) &&
            !$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
            if ($this->autoRetryOnException) {
                return $this->resetRetryableJob($job);
            }
        }
        return false;
    }

    protected function updateJobFailure(BaseRetryableJob $job) {
        $job->setFailures($job->getFailures() + 1);
        if (!$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_FAILURES, $job->getMaxFailures(), $job->getFailures()) &&
            !$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
            if ($this->autoRetryOnFailure) {
                return $this->resetRetryableJob($job);
            }
        }
        return false;
    }

    /**
     * Determine if we've hit a max retry condition
     * @param BaseRetryableJob $job
     * @param $status
     * @param null $max
     * @param int $count
     * @return bool
     */
    protected function updateMaxStatus(BaseRetryableJob $job, $status, $max = null, $count = 0)
    {
        if (null !== $max && $count >= $max) {
            $job->setStatus($status);

            return true;
        }
        return false;
    }

    protected function resetRetryableJob(BaseRetryableJob $job) {
        if ($this->resetJob($job)) {
            $this->getJobTimingManager()->recordTiming(JobTiming::STATUS_INSERT);
            return true;
        }
        return false;
    }

    /**
     * @param BaseRetryableJob $baseRetryableJob
     * @return bool true if the job was successfully "reset", i.e. re-queued
     */
    abstract protected function resetJob(BaseRetryableJob $baseRetryableJob);

    protected function setBaseRetryableJobDefaults(BaseRetryableJob $job) {
        if ($job->getMaxExceptions() === null) {
            $job->setMaxExceptions($this->defaultMaxExceptions);
        }

        if ($job->getMaxRetries() === null) {
            $job->setMaxRetries($this->defaultMaxRetries);
        }

        if ($job->getMaxFailures() === null) {
            $job->setMaxFailures($this->defaultMaxFailures);
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
     * @param int|null $defaultMaxRetry
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
     * @param int|null $defaultMaxFailure
     */
    public function setDefaultMaxFailures($defaultMaxFailures)
    {
        $this->defaultMaxFailure = $defaultMaxFailures;
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
     * @param int|null $defaultMaxException
     */
    public function setDefaultMaxExceptions($defaultMaxExceptions)
    {
        $this->defaultMaxExceptions = $defaultMaxExceptions;
    }

    protected function recordTiming(Job $job)
    {
        $status = JobTiming::STATUS_INSERT;
        if ($job->getWhenAt() && $job->getWhenAt() > (new \DateTime())) {
            $status = JobTiming::STATUS_INSERT_DELAYED;
        }

        $this->getJobTimingManager()->recordTiming($status);
    }
}
