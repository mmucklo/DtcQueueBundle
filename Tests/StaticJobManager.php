<?php

namespace Dtc\QueueBundle\Tests;

use Dtc\QueueBundle\Model\AbstractJobManager;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTimingManager;
use Dtc\QueueBundle\Model\RunManager;

/**
 * @author David Tee
 *
 *	Created for Unitesting purposes.
 */
class StaticJobManager extends AbstractJobManager
{
    private $jobs;
    private $uniqeId;
    public $enableSorting = true;

    public function __construct(RunManager $runManager, JobTimingManager $jobTimingManager, $jobClass)
    {
        $this->jobs = array();
        $this->uniqeId = 1;
        parent::__construct($runManager, $jobTimingManager, $jobClass);
    }

    public function getJobCount($workerName = null, $methodName = null)
    {
        if ($workerName && isset($this->jobs[$workerName])) {
            return count($this->jobs[$workerName]);
        }

        $total = 0;
        foreach ($this->jobs as $jobWorkerName => $jobs) {
            $total += count($this->jobs[$jobWorkerName]);
        }

        return array_sum(array_map(function($jobs) {
            return count($jobs);
        }, $this->jobs));
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

        return $job;
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
