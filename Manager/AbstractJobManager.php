<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;

abstract class AbstractJobManager implements JobManagerInterface
{
    protected $jobTiminigManager;
    protected $jobClass;
    protected $runManager;
    
    /** @var EventDispatcher $eventDispatcher */
    protected $eventDispatcher;
    
    public function __construct(RunManager $runManager, JobTimingManager $jobTimingManager, $jobClass, EventDispatcher $eventDispatcher)
    {
        $this->runManager = $runManager;
        $this->jobTiminigManager = $jobTimingManager;
        $this->jobClass = $jobClass;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return array
     */
    public static function getAllStatuses()
    {
        return [
                BaseJob::STATUS_NEW => 0,
                BaseJob::STATUS_RUNNING => 0,
                BaseJob::STATUS_SUCCESS => 0,
                BaseJob::STATUS_FAILURE => 0,
                BaseJob::STATUS_EXCEPTION => 0,
                \Dtc\QueueBundle\Model\Job::STATUS_EXPIRED => 0, ];
    }

    /**
     * @return array
     *
     * @throws UnsupportedException
     */
    public function getStatus()
    {
        $count = $this->getWaitingJobCount();
        $allStatuses = static::getAllStatuses();
        foreach (array_keys($allStatuses) as $status) {
            $allStatuses[$status] = 'N/A';
        }
        $allStatuses[BaseJob::STATUS_NEW] = $count;

        return ['all' => $allStatuses];
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

    public function resetExceptionJobs($workerName = null, $methodName = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function pruneExceptionJobs($workerName = null, $methodName = null)
    {
        throw new UnsupportedException('Unsupported');
    }

    public function getWaitingJobCount($workerName = null, $methodName = null)
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
