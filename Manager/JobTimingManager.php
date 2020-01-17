<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\UnsupportedException;

class JobTimingManager
{
    /** @var string */
    protected $jobTimingClass;

    /** @var bool */
    protected $recordTimings;

    public function __construct($jobTimingClass, $recordTimings)
    {
        $this->jobTimingClass = $jobTimingClass;
        $this->recordTimings = $recordTimings;
    }

    /**
     * @throws UnsupportedException
     *
     * @return int Number of archived runs pruned
     */
    public function pruneJobTimings(\DateTime $olderThan)
    {
        throw new UnsupportedException('not supported');
    }

    /**
     * Subclasses should overrride this function instead of recordTiming.
     *
     * @param $status
     */
    protected function performRecording($status, \DateTime $dateTime = null)
    {
    }

    /**
     * @param $status
     */
    public function recordTiming($status, \DateTime $dateTime = null)
    {
        if (!$this->recordTimings) {
            return;
        }
        $this->performRecording($status, $dateTime);
    }

    /**
     * @return string
     */
    public function getJobTimingClass()
    {
        return $this->jobTimingClass;
    }
}
