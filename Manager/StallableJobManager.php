<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\StallableJob;

abstract class StallableJobManager extends PriorityJobManager
{
    protected $defaultMaxStalls;

    abstract protected function stallableSave(StallableJob $job);

    abstract public function pruneStalledJobs($workerName = null, $method = null, callable $progressCallback = null);

    abstract public function resetStalledJobs($workerName = null, $method = null, callable $progressCallback = null);

    public static function getAllStatuses()
    {
        $statuses = parent::getAllStatuses();
        $statuses[StallableJob::STATUS_STALLED] = 0;
        $statuses[StallableJob::STATUS_MAX_STALLS] = 0;

        return $statuses;
    }

    protected function prioritySave(Job $job)
    {
        if (!$job instanceof StallableJob) {
            throw new \InvalidArgumentException('Job is not instanceof '.StallableJob::class);
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
     *
     * @return bool true if retry
     */
    abstract protected function stallableSaveHistory(StallableJob $job, $retry);

    /**
     * @return bool true if retry
     *
     * @param Job $job
     */
    protected function retryableSaveHistory(RetryableJob $job, $retry)
    {
        if (!$job instanceof StallableJob) {
            throw new \InvalidArgumentException('job not instance of '.StallableJob::class);
        }

        if ($retry) {
            return $this->stallableSaveHistory($job, $retry);
        }

        if (StallableJob::STATUS_STALLED === $job->getStatus()) {
            return $this->stallableSaveHistory($job, $this->updateJobStalled($job));
        }

        return $this->stallableSaveHistory($job, false);
    }

    /**
     * @param StallableJob $job
     *
     * @return bool false if
     */
    private function updateJobStalled(StallableJob $job)
    {
        return $this->updateJobMax($job, 'Stalls', StallableJob::STATUS_MAX_STALLS, true);
    }

    protected function setStallableJobDefaults(StallableJob $job)
    {
        if (null === $job->getMaxStalls()) {
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
     * @param int|null $defaultMaxStalls
     */
    public function setDefaultMaxStalls($defaultMaxStalls)
    {
        $this->defaultMaxStalls = $defaultMaxStalls;
    }
}
