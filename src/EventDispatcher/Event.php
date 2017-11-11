<?php

namespace Dtc\QueueBundle\EventDispatcher;

use Dtc\QueueBundle\Model\Job;

class Event
{
    const PRE_JOB = 'queue.pre_job';
    const POST_JOB = 'queue.post_job';

    private $job;

    public function __construct(Job $job)
    {
        $this->job = $job;
    }

    /**
     * @param mixed $job
     */
    public function setJob($job)
    {
        $this->job = $job;
    }

    /**
     * @return mixed
     */
    public function getJob()
    {
        return $this->job;
    }
}
