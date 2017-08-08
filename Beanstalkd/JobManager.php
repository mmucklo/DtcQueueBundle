<?php

namespace Dtc\QueueBundle\Beanstalkd;

use Dtc\QueueBundle\Model\Job as BaseJob;
use Dtc\QueueBundle\Model\JobManagerInterface;
use Pheanstalk\Pheanstalk;

class JobManager implements JobManagerInterface
{
    const DEFAULT_RESERVE_TIMEOUT = 5; // seconds

    /** @var Pheanstalk */
    protected $beanstalkd;

    protected $tube;

    protected $reserveTimeout = self::DEFAULT_RESERVE_TIMEOUT;

    public function setBeanstalkd(Pheanstalk $beanstalkd)
    {
        $this->beanstalkd = $beanstalkd;
    }

    public function setTube($tube)
    {
        $this->tube = $tube;
    }

    public function setReserveTimeout($timeout) {
        $this->reserveTimeout = $timeout;
    }

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        /** @var Job $job */
        $arguments = [$job->toMessage(), $job->getPriority(), $job->getDelay(), $job->getTtr()];
        $method = 'put';
        if ($this->tube) {
            array_unshift($arguments, $this->tube);
            $method .= 'InTube';
        }
        var_dump(get_class($this->beanstalkd));
        var_dump($method);
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


        $beanJob = $beanJob->reserve($this->reserveTimeout);
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

        // @Todo Need a strategy for buried jobs, if any?
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

        // @Todo - use statistics
    }

    public function getStats() {
        return $this->beanstalkd->stats();
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
