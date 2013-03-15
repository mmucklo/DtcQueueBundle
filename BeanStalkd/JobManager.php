<?php
namespace Dtc\QueueBundle\BeanStalkd;

use Dtc\QueueBundle\Model\Job as BaseJob;
use Dtc\QueueBundle\Model\JobManagerInterface;

class JobManager
    implements JobManagerInterface
{
    protected $beanstalkd;
    protected $isBuryOnGet;
    public function __construct(\Pheanstalk_Pheanstalk $beanstalkd, $isBuryOnGet = false) {
        $this->beanstalkd = $beanstalkd;
        $this->isBuryOnGet = $isBuryOnGet;
    }

    public function save($job) {
        $jobId = $this->beanstalkd
            ->watch($job->getWorkerName())
            ->put($job->toMessage(), $job->getPriority(), $job->getDelay(), $job->getTTR());

        $job->setId($jobId);
        return $job;
    }

    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {
        if ($methodName) {
            throw new \Exception("Unsupported");
        }

        $beanJob = $this->beanstalkd;
        if ($workerName) {
            $beanJob = $beanJob->watch($workerName);
        }

        $beanJob = $beanJob->reserve();
        if ($beanJob) {
            if ($this->isBuryOnGet) {
                $this->beanstalkd->bury($beanJob);
            }

            $job = new Job();
            $job->fromMessage($beanJob->getData());
            $job->setId($beanJob->getId());
            return $job;
        }
    }

    public function deleteJob($job) {
        $this->beanstalkd
            ->delete($job);
    }

    // Save History get called upon completion of the job
    public function saveHistory($job) {
        if ($job->getStatus() === BaseJob::STATUS_SUCCESS) {
            $this->beanstalkd
                ->delete($job);
        }
        else {
            $this->beanstalkd
                ->bury($job);
        }
    }

    public function getJobCount($workerName = null, $methodName = null) {
        if ($methodName) {
            throw new \Exception("Unsupported");
        }

        if ($workerName) {
            throw new \Exception("Unsupported");
        }
    }

    public function resetErroneousJobs($workerName = null, $methodName = null) {
        throw new \Exception("Unsupported");
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null) {
        throw new \Exception("Unsupported");
    }

    public function getStatus() {
        throw new \Exception("Unsupported");
    }
}
