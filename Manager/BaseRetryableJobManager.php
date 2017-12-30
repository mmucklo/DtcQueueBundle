<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\BaseRetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\RetryableJob;

abstract class BaseRetryableJobManager extends AbstractJobManager {

    protected $defaultMaxRetry;
    protected $defaultMaxFailure;

    protected $autoRetryOnFailure;

    abstract public function retryableSave(BaseRetryableJob $job);

    public function save(Job $job) {
        if (!$job instanceof BaseRetryableJob) {
            throw new \InvalidArgumentException("Job is not instanceof " . BaseRetryableJob::class);
        }

        if (!$job->getId()) {
            $this->setBaseRetryableJobDefaults($job);
        }

        return $this->retryableSave($job);
    }

    /**
     * @param bool $retry true if the job was retried, false if not
     */
    abstract protected function retryableSaveHistory($retry);

    public function saveHistory(Job $job) {
        if (!$job instanceof BaseRetryableJob) {
            throw new \InvalidArgumentException("job not instance of " . BaseRetryableJob::class);
        }

        $retried = false;
        if ($job->getStatus() === BaseJob::STATUS_FAILURE) {
            $retried = $this->updateJobFailure($job);
        }

        $this->retryableSaveHistory($retried);
    }

    protected function updateJobFailure(BaseRetryableJob $job) {
        $job->setFailureCount($job->getFailureCount() + 1);
        if (!$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_FAILURE, $job->getMaxFailure(), $job->getFailureCount()) &&
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

    abstract protected function resetJob(BaseRetryableJob $baseRetryableJob);

    protected function setBaseRetryableJobDefaults(BaseRetryableJob $job) {
        if ($job->getMaxRetries() === null) {
            if (isset($this->retryableDefaults['retries_max'])) {
                $job->setMaxRetries($this->retryableDefaults['retries_max']);
            }
        }

        if ($job->getMaxFailure() === null) {
            if (isset($this->retryableDefaults['failure_max'])) {
                $job->setMaxFailure($this->retryableDefaults['failure_max']);
            }
        }
    }

    /**
     * @return int|null
     */
    public function getDefaultMaxRetry()
    {
        return $this->defaultMaxRetry;
    }

    /**
     * @param int|null $defaultMaxRetry
     */
    public function setDefaultMaxRetry($defaultMaxRetry)
    {
        $this->defaultMaxRetry = $defaultMaxRetry;
    }

    /**
     * @return int|null
     */
    public function getDefaultMaxFailure()
    {
        return $this->defaultMaxFailure;
    }

    /**
     * @param int|null $defaultMaxFailure
     */
    public function setDefaultMaxFailure($defaultMaxFailure)
    {
        $this->defaultMaxFailure = $defaultMaxFailure;
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
}
