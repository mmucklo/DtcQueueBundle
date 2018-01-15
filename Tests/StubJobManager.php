<?php

namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Manager\AbstractJobManager;
use Dtc\QueueBundle\Model\Job;

class StubJobManager extends AbstractJobManager
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

    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneExpiredJobs($workerName = null, $methodName = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function pruneStalledJobs($workerName = null, $method = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function deleteJob(Job $job)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }

    public function getJobCount($workerName = null, $methodName = null)
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

    public function resetStalledJobs($workerName = null, $method = null)
    {
        return $this->recordArgs(__FUNCTION__, func_get_args());
    }
}
