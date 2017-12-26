<?php

namespace Dtc\QueueBundle\Manager;

use Doctrine\ORM\Query\Expr\Base;
use Dtc\QueueBundle\Model\BaseRetryableJob;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\RetryableJob;

class RetryableJobManager extends AbstractJobManager {

    protected $retryableDefaults;
    public function setRetryableDefaults(array $retryableDefaults) {
        $this->retryableDefaults = $retryableDefaults;
    }

    abstract public function retryableSave();

    public function save(Job $job) {
        if (!$job instanceof BaseRetryableJob) {
            throw new \InvalidArgumentException("Job is not instanceof " . BaseRetryableJob::class);
        }

        if (!$job->getId()) {
            $this->setBaseRetryableJobDefaults($job);
            if ($job instanceof RetryableJob) {
                $this->setRetryableJobDefaults($job);
            }
        }
        
        return $this->retryableSave($job);
    }

    public function saveHistory() {
        if (!$job instanceof BaseRetryableJob) {
            throw new \InvalidArgumentException("job not instance of " . BaseRetryableJob::class);
        }

        if ($job->getStatus() === BaseJob::STATUS_FAILURE) {
            $job->setFailureCount($job->getFailureCount() + 1);
            if (!$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_FAILURE, $job->getMaxFailure(), $job->getFailureCount()) &&
                !$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
                if ($this->resetJob($job)) {
                    $this->getJobTimingManager()->recordTiming(JobTiming::STATUS_INSERT);
                    return false;
                }
            }
        }
        return true;
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

    protected function resetRetryableJob(BaseRetryableJob $baseRetryableJob) {
        if ($this->resetJob($job)) {
            $this->getJobTimingManager()->recordTiming(JobTiming::STATUS_INSERT);
            return false;
        }
    }

    abstract protected function resetJob(BaseRetryableJob $baseRetryableJob);

    public function setBaseRetryableJobDefaults(BaseRetryableJob $job) {
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
    
    
    public function setRetryableJobDefaults(RetryableJob $job) {
        if ($job->getMaxException() === null) {
            if (isset($this->retryableDefaults['exception_max'])) {
                $job->setMaxException($this->retryableDefaults['exception_max']);
            }
        }
        
        if ($job->getMaxStalled() === null) {
            if (isset($this->retryableDefaults['stalled_max']) {
                $job->setMaxStalled($this->retryableDefaults['stalled_max']);
            }
        }
    }
    
}


