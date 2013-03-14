<?php
namespace Dtc\QueueBundle\Model;
abstract class Worker
{
    protected $jobManager;
    protected $jobClass;
    protected $job;

    /**
     * @return the $jobClass
     */
    public function getJobClass()
    {
        return $this->jobClass;
    }

    /**
     * @param field_type $jobClass
     */
    public function setJobClass($jobClass)
    {
        $this->jobClass = $jobClass;
    }

    public function setJobManager(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @return the $jobManager
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

        $dateTime = new \DateTime();
        $dateTime->setTimestamp($time);

        return new $this->jobClass($this, $dateTime, $batch, $priority);
    }

    public function later($delay = 0, $priority = null)
    {
        return $this->at(time() + $delay, false, $priority);
    }

    public function batchLater($delay = 0, $priority = null)
    {
        return $this->at($delay, true, $priority);
    }

    public function batchAt($time = null, $priority = null)
    {
        return $this->at($time, true, $priority);
    }

    abstract function getName();
}
