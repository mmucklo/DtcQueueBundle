<?php

namespace Dtc\QueueBundle\Beanstalkd;

use Dtc\QueueBundle\Model\AbstractJobManager;
use Dtc\QueueBundle\Model\Job as BaseJob;
use Pheanstalk\Pheanstalk;

class JobManager extends AbstractJobManager
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

    public function setReserveTimeout($timeout)
    {
        $this->reserveTimeout = $timeout;
    }

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        /** @var Job $job */
        $message = $job->toMessage();
        $arguments = [$message, $job->getPriority(), $job->getDelay(), $job->getTtr()];
        $method = 'put';
        if ($this->tube) {
            array_unshift($arguments, $this->tube);
            $method .= 'InTube';
        }
        $jobId = call_user_func_array([$this->beanstalkd, $method], $arguments);
        $job->setId($jobId);

        // Ideally we should get this from beanstalk, but to save the roundtrip time, we do this here
        $job->setBeanJob($this->getBeanJob($jobId, $message));

        return $job;
    }

    public function getBeanJob($jobId, $data)
    {
        return new \Pheanstalk\Job($jobId, $data);
    }

    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {
        if ($methodName) {
            throw new \Exception('Unsupported');
        }

        $beanstalkd = $this->beanstalkd;
        if ($this->tube) {
            $beanstalkd = $this->beanstalkd->watch($this->tube);
        }

        $expiredJob = false;

        do {
            $beanJob = $beanstalkd->reserve($this->reserveTimeout);
            if ($beanJob) {
                $job = new Job();
                $job->fromMessage($beanJob->getData());
                $job->setId($beanJob->getId());

                if (($expiresAt = $job->getExpiresAt()) && $expiresAt->getTimestamp() < time()) {
                    $expiredJob = true;
                    $this->beanstalkd->delete($beanJob);
                    continue;
                }
                $job->setBeanJob($beanJob);

                return $job;
            }
        } while ($expiredJob);
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        $id = $job->getId();

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
    }

    public function pruneExpiredJobs()
    {
        throw new \Exception('Not Supported');
    }

    public function getStats()
    {
        return $this->beanstalkd->stats();
    }

    public function pruneArchivedJobs(\DateTime $olderThan)
    {
        throw new \Exception('Not Supported');
    }
}
