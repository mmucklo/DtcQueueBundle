<?php

namespace Dtc\QueueBundle\Model;

interface JobManagerInterface
{
    public function resetErroneousJobs($workerName = null, $methodName = null);

    public function pruneErroneousJobs($workerName = null, $methodName = null);

    /**
     * Prunes (or archived) jobs that are expired.
     *
     * @return mixed
     */
    public function pruneExpiredJobs($workerName = null, $methodName = null);

    public function getJobCount($workerName = null, $methodName = null);

    public function getStatus();

    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null);

    public function deleteJob(Job $job);

    public function save(Job $job);

    public function saveHistory(Job $job);

    public function resetStalledJobs($workerName = null, $method = null);

    public function pruneStalledJobs($workerName = null, $method = null);

    /**
     * Removes archived jobs older than $olderThan.
     *
     * @param \DateTime $olderThan
     */
    public function pruneArchivedJobs(\DateTime $olderThan);

    //public function deleteJobById($jobId);
}
