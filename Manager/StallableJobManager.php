<?php

namespace Dtc\QueueBundle\Manager;

use Doctrine\ORM\Query\Expr\Base;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\BaseRetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\StallableJob;

abstract class StallableJobManager extends PriorityJobManager {

    protected $defaultMaxStalls;

    abstract protected function stallableSave(StallableJob $job);

    protected function prioritySave(Job $job) {
        if (!$job instanceof StallableJob) {
            throw new \InvalidArgumentException("Job is not instanceof " . StallableJob::class);
        }

        if (!$job->getId()) {
            if ($job instanceof StallableJob) {
                $this->setStallableJobDefaults($job);
            }
        }
        
        return $this->stallableSave($job);
    }

    /**
     * @param StallableJob $job
     * @param $retry true if retry
     * @return bool true if retry
     */
    abstract protected function stallableSaveHistory(StallableJob $job, $retry);

    /**
     * @return bool true if retry
     * @param Job $job
     */
    protected function retryableSaveHistory(BaseRetryableJob $job, $retry) {
        if (!$job instanceof StallableJob) {
            throw new \InvalidArgumentException("job not instance of " . StallableJob::class);
        }

        if ($retry) {
            return $this->stallableSaveHistory($job, $retry);
        }
        
        if ($job->getStatus() === StallableJob::STATUS_STALLED) {
            return $this->stallableSaveHistory($job, $this->updateJobStalled($job));
        }
        return $this->stallableSaveHistory($job, false);
    }


    /**
     * @param StallableJob $job
     * @return bool false if
     */
    protected function updateJobStalled(StallableJob $job) {
        $job->setStalls($job->getStalls() + 1);
        if (!$this->updateMaxStatus($job, StallableJob::STATUS_MAX_STALLS, $job->getMaxStalls(), $job->getStalls()) &&
            !$this->updateMaxStatus($job, BaseRetryableJob::STATUS_MAX_RETRIES, $job->getMaxRetries(), $job->getRetries())) {
            return $this->resetRetryableJob($job);
        }
        return false;
    }

    protected function setStallableJobDefaults(StallableJob $job) {
        if ($job->getMaxStalls() === null) {
            $job->setMaxStalls($this->defaultMaxStalls);
        }
    }


    /**
     * @return int|null
     */
    public function getDefaultMaxStalls()
    {
        return $this->defaultMaxStalls;
    }

    /**
     * @param int|null $defaultMaxStalled
     */
    public function setDefaultMaxStalls($defaultMaxStalls)
    {
        $this->defaultMaxStalls = $defaultMaxStalls;
    }

}
