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

    public function at($time = null, $batch = false, $priority = null)
    {
        if ($time === null) {
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

    public function later($delay = 0, $priority = null)
    {
        $job = $this->at(time() + $delay, false, $priority);
        $job->setDelay($delay);

        return $job;
    }

    public function batchLater($delay = 0, $priority = null)
    {
        $job = $this->at($delay, true, $priority);
        $job->setDelay($delay);

        return $job;
    }

    public function batchAt($time = null, $priority = null)
    {
        return $this->at($time, true, $priority);
    }

    abstract public function getName();
}
