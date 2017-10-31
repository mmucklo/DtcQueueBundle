<?php

namespace Dtc\QueueBundle\Model;

class RunManager
{
    /** @var string */
    protected $runClass;

    /** @var string */
    protected $jobTimingClass;

    /** @var bool */
    protected $recordTimings;

    public function __construct($runClass, $jobTimingClass, $recordTimings)
    {
        $this->runClass = $runClass;
        $this->jobTimingClass = $jobTimingClass;
        $this->recordTimings = $recordTimings;
    }

    /**
     * @return string
     */
    public function getRunClass()
    {
        return $this->runClass;
    }

    /**
     * @param string $runClass
     */
    public function setRunClass($runClass)
    {
        $this->runClass = $runClass;
    }

    /**
     * @param \DateTime $olderThan
     *
     * @return int Number of archived runs pruned
     */
    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        throw new \Exception('not supported');
    }

    /**
     * @param \DateTime $olderThan
     *
     * @return int Number of archived runs pruned
     */
    public function pruneJobTimings(\DateTime $olderThan)
    {
        throw new \Exception('not supported');
    }

    /**
     * @return null|string
     */
    public function getJobTimingClass()
    {
        return $this->jobTimingClass;
    }

    /**
     * @param string $jobTimingClass
     */
    public function setJobTimingClass($jobTimingClass)
    {
        $this->jobTimingClass = $jobTimingClass;
    }

    public function recordJobRun(Job $job)
    {
    }
}
