<?php

namespace Dtc\QueueBundle\ORM;

class StubLiveJobsGridSource
{
    protected $jobManager;
    protected $running = false;
    private $columnSource;

    public function getId()
    {
        return 'dtc_queue.grid_source.jobs_'.($this->isRunning() ? 'running' : 'waiting').'.orm';
    }

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    /**
     * @param bool $flag
     */
    public function setRunning($flag)
    {
        $this->running = $flag;
    }

    public function isRunning()
    {
        return $this->running;
    }
}
