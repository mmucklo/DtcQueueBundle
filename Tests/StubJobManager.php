<?php

namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Manager\StallableJobManager;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\StallableJob;

class StubJobManager extends StallableJobManager
{
    use RecordingTrait;

    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function saveHistory(Job $job)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function save(Job $job)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    protected function stallableSave(StallableJob $job)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    protected function resetJob(RetryableJob $job)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    protected function stallableSaveHistory(StallableJob $job, $retry)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneExpiredJobs($workerName = null, $methodName = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneExceptionJobs($workerName = null, $methodName = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneStalledJobs($workerName = null, $method = null, callable $progressCallback = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function deleteJob(Job $job)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function getWaitingJobCount($workerName = null, $methodName = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function getStatus()
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function resetExceptionJobs($workerName = null, $methodName = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function resetStalledJobs($workerName = null, $method = null, callable $progressCallback = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }
}
