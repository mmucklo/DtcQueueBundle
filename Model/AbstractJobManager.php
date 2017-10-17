<?php

namespace Dtc\QueueBundle\Model;

abstract class AbstractJobManager implements JobManagerInterface
{
    abstract public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null);

    abstract public function save(Job $job);

    abstract public function saveHistory(Job $job);

    public function resetStalledJobs($workerName = null, $method = null)
    {
        throw new \Exception('Unsupported');
    }

    public function pruneStalledJobs($workerName = null, $method = null)
    {
        throw new \Exception('Unsupported');
    }

    public function resetErroneousJobs($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getStatus()
    {
        throw new \Exception('Unsupported');
    }

    public function getJobCount($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    public function deleteJob(Job $job)
    {
        throw new \Exception('Unsupported');
    }

    public function pruneExpiredJobs($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        throw new \Exception('Unsupported');
    }
}
