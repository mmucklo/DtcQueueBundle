<?php

namespace Dtc\QueueBundle\Beanstalkd;

use Dtc\QueueBundle\Manager\RetryableJobManager;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Job as BaseJob;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Util\Util;
use Pheanstalk\Pheanstalk;

class JobManager extends RetryableJobManager
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

    public function retryableSave(RetryableJob $job)
    {
        if (!$job instanceof Job) {
            throw new \InvalidArgumentException('$job must be of type: '.Job::class);
        }

        return $this->putJob($job);
    }

    protected function putJob(Job $job)
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

    protected function resetJob(RetryableJob $job)
    {
        if (!$job instanceof Job) {
            throw new \InvalidArgumentException('$job must be instance of '.Job::class);
        }
        $job->setStatus(BaseJob::STATUS_NEW);
        $job->setMessage(null);
        $job->setStartedAt(null);
        $job->setRetries($job->getRetries() + 1);
        $job->setUpdatedAt(Util::getMicrotimeDateTime());
        $this->putJob($job);

        return true;
    }

    public function getBeanJob($jobId, $data)
    {
        return new \Pheanstalk\Job($jobId, $data);
    }

    /**
     * @param string|null     $workerName
     * @param string|null     $methodName
     * @param bool            $prioritize
     * @param int|string|null $runId
     *
     * @return Job|null
     *
     * @throws UnsupportedException
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        if (null !== $methodName) {
            throw new UnsupportedException('Unsupported');
        }
        if (null !== $workerName) {
            throw new UnsupportedException('Unsupported');
        }

        $beanstalkd = $this->beanstalkd;
        if ($this->tube) {
            $beanstalkd = $this->beanstalkd->watch($this->tube);
        }

        do {
            $expiredJob = false;
            $job = $this->findJob($beanstalkd, $expiredJob, $runId);
        } while ($expiredJob);

        return $job;
    }

    /**
     * @param Pheanstalk      $beanstalkd
     * @param bool            $expiredJob
     * @param int|string|null $runId
     *
     * @return Job|null
     */
    protected function findJob(Pheanstalk $beanstalkd, &$expiredJob, $runId)
    {
        $beanJob = $beanstalkd->reserve($this->reserveTimeout);
        if ($beanJob) {
            $job = new Job();
            $job->fromMessage($beanJob->getData());
            $job->setId($beanJob->getId());
            $job->setRunId($runId);

            if (($expiresAt = $job->getExpiresAt()) && $expiresAt->getTimestamp() < time()) {
                $expiredJob = true;
                $beanstalkd->delete($beanJob);

                return null;
            }
            $job->setBeanJob($beanJob);

            return $job;
        }

        return null;
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        $this->beanstalkd
            ->delete($job);
    }

    // Save History get called upon completion of the job
    public function retryableSaveHistory(RetryableJob $job, $retry)
    {
        if (!$retry) {
            $this->beanstalkd
                ->delete($job);
        }
    }

    public function getStats()
    {
        return $this->beanstalkd->stats();
    }
}
