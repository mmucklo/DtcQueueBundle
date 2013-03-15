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
    public function deleteJob($job);
    public function save($job);
    public function saveHistory($job);

    //public function deleteJobById($jobId);
}

