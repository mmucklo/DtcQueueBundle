<?php
namespace Dtc\QueueBundle\Test;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;

class StaticJobManager
    implements JobManagerInterface
{
    private $jobs;
    private $uniqeId;

    public function __construct() {
        $this->jobs = array();
        $this->uniqeId = 1;
    }

    public function resetErroneousJobs($workerName = null, $methodName = null) {
        return null;
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null) {
        return null;
    }

    public function getJobCount($workerName = null, $methodName = null) {
        if ($workerName && isset($this->jobs[$workerName])) {
            return $this->jobs[$workerName];
        }

        $total = 0;
        for ($this->jobs as $jobWorkerName => $jobs) {
            $total += count($this->jobs[$jobWorkerName]);
        }

        return count($this->jobs[$workerName]);
    }

    public function getStatus() {
        return null;
    }

    public function deleteJob(Job $job) {
        unset($this->allJobs[$job->getId()]);
        unset($this->jobs[$job->getWorkerName()][$job->getId()]);
    }

    public function getJob($workerName = null, $methodName = null, $prioritize = true) {
        if ($workerName && isset($this->jobs[$workerName])) {
            return array_pop($this->jobs[$workerName]);
        }
    }

    public function save(Job $job) {
        if (!$job->getId()) {
            $job->setId($this->uniqeId);
            $this->jobs[$job->getWorkerName()][$this->uniqeId]  = $job;
            $this->uniqeId++;
        }
    }

    public function saveHistory(Job $job) {
        $this->save($job);
    }
}
