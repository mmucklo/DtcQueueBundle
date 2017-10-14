<?php

namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;

/**
 * @author David Tee
 *
 *	Created for Unitesting purposes.
 */
class StaticJobManager implements JobManagerInterface
{
    private $jobs;
    private $uniqeId;
    public $enableSorting = true;

    public function __construct()
    {
        $this->jobs = array();
        $this->uniqeId = 1;
    }

    public function resetErroneousJobs($workerName = null, $methodName = null)
    {
        return null;
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null)
    {
        return null;
    }

    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        return 0;
    }

    public function pruneExpiredJobs()
    {
        return 0;
    }

    public function getJobCount($workerName = null, $methodName = null)
    {
        if ($workerName && isset($this->jobs[$workerName])) {
            return $this->jobs[$workerName];
        }

        $total = 0;
        foreach ($this->jobs as $jobWorkerName => $jobs) {
            $total += count($this->jobs[$jobWorkerName]);
        }

        return count($this->jobs[$workerName]);
    }

    public function getStatus()
    {
        return null;
    }

    public function deleteJob(Job $job)
    {
        unset($this->jobs[$job->getWorkerName()][$job->getId()]);
    }

    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        if ($workerName && isset($this->jobs[$workerName])) {
            return array_pop($this->jobs[$workerName]);
        }

        foreach ($this->jobs as $jobWorkerName => &$jobs) {
            if ($jobs) {
                return array_pop($jobs);
            }
        }
    }

    public function save(Job $job)
    {
        if (!$job->getId()) {
            $job->setId($this->uniqeId);
            $this->jobs[$job->getWorkerName()][$this->uniqeId] = $job;
            if ($this->enableSorting) {
                uasort($this->jobs[$job->getWorkerName()], array($this, 'compareJobPriority'));
            }

            ++$this->uniqeId;
        }
    }

    public function compareJobPriority(Job $a, Job $b)
    {
        return $b->getPriority() - $a->getPriority();
    }

    public function saveHistory(Job $job)
    {
        $this->save($job);
    }
}
