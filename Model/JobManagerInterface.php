<?php
namespace Dtc\QueueBundle\Model;

use Doctrine\ODM\MongoDB\DocumentRepository;
use Doctrine\ODM\MongoDB\DocumentManager;

interface JobManagerInterface
{
    public function resetErroneousJobs($workerName = null, $methodName = null);
    public function pruneErroneousJobs($workerName = null, $methodName = null);
    public function getJobCount($workerName = null, $methodName = null);
    public function getStatus();
    public function getJob($workerName = null, $methodName = null, $prioritize = true);
    public function deleteJob(Job $job);
    public function save(Job $job);
    public function saveHistory(Job $job);

    //public function deleteJobById($jobId);
}

