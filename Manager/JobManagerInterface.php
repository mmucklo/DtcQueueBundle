<?php

namespace Dtc\QueueBundle\Manager;

interface JobManagerInterface
{
    public function resetExceptionJobs($workerName = null, $methodName = null);

    public function pruneExceptionJobs($workerName = null, $methodName = null);

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

    /**
     * Called after a job has finished - may delete the job / reset the job and/or do other related cleanup
     * @param Job $job
     * @return bool Whether the job is deletable or not (true - job can be deleted, false - job was reset or reused in some way)
     */
    public function saveHistory(Job $job);

    public function resetStalledJobs($workerName = null, $method = null);

    public function pruneStalledJobs($workerName = null, $method = null);

    /**
     * @return JobTimingManager
     */
    public function getJobTimingManager();

    /**
     * Removes archived jobs older than $olderThan.
     *
     * @param \DateTime $olderThan
     */
    public function pruneArchivedJobs(\DateTime $olderThan);
}
