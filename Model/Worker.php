<?php

namespace Dtc\QueueBundle\Model;

abstract class Worker
{
    protected $jobManager;
    protected $jobClass;
    protected $job;

    /**
     * @return string
     */
    public function getJobClass()
    {
        return $this->jobClass;
    }

    /**
     * @param string $jobClass
     */
    public function setJobClass($jobClass)
    {
        $this->jobClass = $jobClass;
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
        if (null === $time) {
            $time = time();
        }

        if ($time) {
            $dateTime = new \DateTime();
            $dateTime->setTimestamp($time);
        } else {
            $dateTime = null;
        }

        return new $this->jobClass($this, $batch, $priority, $dateTime);
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
        $job = $this->at(time() + $delay, $batch, $priority);
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
