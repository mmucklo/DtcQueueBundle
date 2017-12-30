<?php

namespace Dtc\QueueBundle\Manager;

use Doctrine\ORM\Query\Expr\Base;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\BaseRetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\RetryableJob;

abstract class RetryableJobManager extends BaseRetryableJobManager {

    protected $defaultMaxException;
    protected $defaultMaxStalled;

    protected $autoRetryOnException;

    public function save(Job $job) {
        if (!$job instanceof RetryableJob) {
            throw new \InvalidArgumentException("Job is not instanceof " . BaseRetryableJob::class);
        }

        if (!$job->getId()) {
            if ($job instanceof RetryableJob) {
                $this->setRetryableJobDefaults($job);
            }
        }
        
        return parent::save($job);
    }

    /**
     * @param Job $job
     * @return bool
     */
    public function saveHistory(Job $job) {
        if (!$job instanceof RetryableJob) {
            throw new \InvalidArgumentException("job not instance of " . BaseRetryableJob::class);
        }

        switch ($job->getStatus()) {
            case RetryableJob::STATUS_STALLED:
                $this->retryableSaveHistory($this->updateJobStalled($job));
                return;
            case BaseJob::STATUS_EXCEPTION:
                $this->retryableSaveHistory($this->updateJobException($job));
                return;
        }
        parent::saveHistory($job);
    }

    protected function updateJobException(RetryableJob $job) {
        $job->setExceptionCount($job->getExceptionCount() + 1);
        if (!$this->updateMaxStatus($job, RetryableJob::STATUS_MAX_EXCEPTION, $job->getMaxException(), $job->getExceptionCount()) &&
            !$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
            if ($this->autoRetryOnException) {
                return $this->resetRetryableJob($job);
            }
        }
        return false;
    }

    /**
     * @param RetryableJob $job
     * @return bool false if
     */
    protected function updateJobStalled(RetryableJob $job) {
        $job->setStalledCount($job->getStalledCount() + 1);
        if (!$this->updateMaxStatus($job, RetryableJob::STATUS_MAX_STALLED, $job->getMaxStalled(), $job->getStalledCount()) &&
            !$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
            return $this->resetRetryableJob($job);
        }
        return false;
    }

    protected function resetRetryableJob(BaseRetryableJob $job) {
        if ($this->resetJob($job)) {
            $this->getJobTimingManager()->recordTiming(JobTiming::STATUS_INSERT);
            return false;
        }
    }
    
    protected function setRetryableJobDefaults(RetryableJob $job) {
        if ($job->getMaxException() === null) {
            if (isset($this->retryableDefaults['exception_max'])) {
                $job->setMaxException($this->retryableDefaults['exception_max']);
            }
        }
        
        if ($job->getMaxStalled() === null) {
            if (isset($this->retryableDefaults['stalled_max'])) {
                $job->setMaxStalled($this->retryableDefaults['stalled_max']);
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
     * @return int|null
     */
    public function getDefaultMaxException()
    {
        return $this->defaultMaxException;
    }

    /**
     * @param int|null $defaultMaxException
     */
    public function setDefaultMaxException($defaultMaxException)
    {
        $this->defaultMaxException = $defaultMaxException;
    }

    /**
     * @return int|null
     */
    public function getDefaultMaxStalled()
    {
        return $this->defaultMaxStalled;
    }

    /**
     * @param int|null $defaultMaxStalled
     */
    public function setDefaultMaxStalled($defaultMaxStalled)
    {
        $this->defaultMaxStalled = $defaultMaxStalled;
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
}
