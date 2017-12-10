<?php

namespace Dtc\QueueBundle\Model;

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
     * @param \DateTime $olderThan
     *
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
     * @param \DateTime|null $dateTime
     */
    protected function performRecording($status, \DateTime $dateTime = null)
    {
    }

    /**
     * @param $status
     * @param \DateTime|null $dateTime
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
