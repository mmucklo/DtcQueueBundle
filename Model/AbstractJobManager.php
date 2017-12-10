<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Exception\UnsupportedException;

abstract class AbstractJobManager implements JobManagerInterface
{
    protected $jobTiminigManager;
    protected $jobClass;
    protected $runManager;

    public function __construct(RunManager $runManager, JobTimingManager $jobTimingManager, $jobClass)
    {
        $this->runManager = $runManager;
        $this->jobTiminigManager = $jobTimingManager;
        $this->jobClass = $jobClass;
    }

    /**
     * @return string
     */
    public function getJobClass()
    {
        return $this->jobClass;
    }

    /**
     * @return JobTimingManager
     */
    public function getJobTimingManager()
    {
        return $this->jobTiminigManager;
    }

    /**
     * @return RunManager
     */
    public function getRunManager()
    {
        return $this->runManager;
    }

    abstract public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null);

    abstract public function save(Job $job);

    abstract public function saveHistory(Job $job);

    public function resetStalledJobs($workerName = null, $method = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function pruneStalledJobs($workerName = null, $method = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function resetErroneousJobs($workerName = null, $methodName = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    /**
     * @return array
     *
     * @throws UnsupportedException
     */
    public function getStatus()
    {
        throw new UnsupportedException('Unsupported');
    }

    public function getJobCount($workerName = null, $methodName = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function deleteJob(Job $job)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function pruneExpiredJobs($workerName = null, $methodName = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        throw new UnsupportedException('Unsupported');
    }
}
