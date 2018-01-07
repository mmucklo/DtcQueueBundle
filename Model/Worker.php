<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Manager\JobManagerInterface;
use Dtc\QueueBundle\Util\Util;

abstract class Worker
{
    const RESULT_SUCCESS = 0;
    const RESULT_FAILURE = 1;

    /** @var JobManagerInterface */
    private $jobManager;
    private $currentJob;

    public function setCurrentJob(BaseJob $job)
    {
        $this->currentJob = $job;
    }

    public function getCurrentJob()
    {
        return $this->currentJob;
    }

    /**
     * @param JobManagerInterface $jobManager
     */
    public function setJobManager(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @return
     */
    public function getJobManager()
    {
        return $this->jobManager;
    }

    /**
     * @param int|null $time
     * @param bool     $batch
     * @param int|null $priority
     */
    public function at($time = null, $batch = false, $priority = null)
    {
        $timeU = $time;
        if (null === $time) {
            $timeU = Util::getMicrotimeStr();
        } elseif (false === strpos(strval($time), '.')) {
            $timeU = strval($time).'.000000';
        }

        $dateTime = \DateTime::createFromFormat('U.u', (string) $timeU);
        if (!$dateTime) {
            throw new \InvalidArgumentException("Invalid time: $time".($timeU != $time ? " - (micro: $timeU)" : ''));
        }
        $jobClass = $this->jobManager->getJobClass();

        return new $jobClass($this, $batch, $priority, $dateTime);
    }

    /**
     * @param int      $delay    Amount of time to delay
     * @param int|null $priority
     */
    public function later($delay = 0, $priority = null)
    {
        return $this->batchOrLaterDelay($delay, false, $priority);
    }

    public function batchOrLaterDelay($delay = 0, $batch = false, $priority = null)
    {
        $job = $this->at(microtime(true) + $delay, $batch, $priority);
        $job->setDelay($delay);

        return $job;
    }

    /**
     * @param int      $delay    Amount of time to delay
     * @param int|null $priority
     */
    public function batchLater($delay = 0, $priority = null)
    {
        return $this->batchOrLaterDelay($delay, true, $priority);
    }

    /**
     * @param int|null $time
     * @param int|null $priority
     */
    public function batchAt($time = null, $priority = null)
    {
        return $this->at($time, true, $priority);
    }

    abstract public function getName();
}
