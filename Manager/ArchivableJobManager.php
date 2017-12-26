<?php

namespace Dtc\QueueBundle\Manager;

abstract class ArchivableJobManager extends PriorityJobManager
{
    /**
     * @var string
     */
    protected $jobArchiveClass;

    /**
     * BaseJobManager constructor.
     *
     * @param RunManager       $runManager
     * @param JobTimingManager $jobTimingManager
     * @param $jobClass
     * @param $jobArchiveClass
     */
    public function __construct(
        RunManager $runManager,
        JobTimingManager $jobTimingManager,
        $jobClass,
        $jobArchiveClass
    ) {
        $this->jobArchiveClass = $jobArchiveClass;
        parent::__construct($runManager, $jobTimingManager, $jobClass);
    }

    /**
     * @return string
     */
    public function getJobArchiveClass()
    {
        return $this->jobArchiveClass;
    }
}
