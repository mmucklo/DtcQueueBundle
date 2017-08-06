<?php

namespace Dtc\QueueBundle\BeanStalkd;

use Dtc\QueueBundle\Model\Job as BaseJob;
use Dtc\QueueBundle\Model\JobManagerInterface;
use Pheanstalk\Pheanstalk;

class JobManager implements JobManagerInterface
{
    /** @var Pheanstalk */
    protected $beanstalkd;
    protected $tube;

    public function setBeanstalkd(Pheanstalk $beanstalkd)
    {
        $this->beanstalkd = $beanstalkd;
    }

    public function setTube($tube)
    {
        $this->tube = $tube;
    }

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        /** @var Job $job */
        $arguments = [$job->toMessage(), $job->getPriority(), $job->getDelay(), $job->getTTR()];
        $method = 'put';
        if ($this->tube) {
            array_unshift($arguments, $this->tube);
            $method .= 'InTube';
        }
        $jobId = call_user_func_array([$this->beanstalkd, $method], $arguments);

        $job->setId($jobId);

        return $job;
    }

    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {
        if ($methodName) {
            throw new \Exception('Unsupported');
        }

        $beanJob = $this->beanstalkd;
        if ($this->tube) {
            $beanJob = $beanJob->watch($this->tube);
        }

        $beanJob = $beanJob->reserve();
        if ($beanJob) {
            $job = new Job();
            $job->fromMessage($beanJob->getData());
            $job->setId($beanJob->getId());

            return $job;
        }
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        $this->beanstalkd
            ->delete($job);
    }

    // Save History get called upon completion of the job
    public function saveHistory(\Dtc\QueueBundle\Model\Job $job)
    {
        if ($job->getStatus() === BaseJob::STATUS_SUCCESS) {
            $this->beanstalkd
                ->delete($job);
        }

        // @Todo Need a strategy for buried jobs
//        else {
//            $this->beanstalkd
//                ->bury($job);
//        }
    }

    public function getJobCount($workerName = null, $methodName = null)
    {
        if ($methodName) {
            throw new \Exception('Unsupported');
        }

        if ($workerName) {
            throw new \Exception('Unsupported');
        }

        $stats = $this->beanstalkd->stats();

        // @Todo - report statistics
    }

    public function resetErroneousJobs($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    public function getStatus()
    {
        throw new \Exception('Unsupported');
    }
}
