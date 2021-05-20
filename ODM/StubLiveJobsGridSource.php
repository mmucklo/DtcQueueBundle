<?php

namespace Dtc\QueueBundle\ODM;

class StubLiveJobsGridSource
{
    protected $jobManager;
    protected $running = false;

    public function getId()
    {
        return 'dtc_queue.grid_source.jobs_'.($this->isRunning() ? 'running' : 'waiting').'.odm';
    }

    public function setRunning($flag)
    {
        $this->running = $flag;
    }

    public function isRunning()
    {
        return $this->running;
    }

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
    }
}
