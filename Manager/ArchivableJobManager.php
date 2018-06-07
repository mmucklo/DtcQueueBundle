<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\EventDispatcher\EventDispatcher;

abstract class ArchivableJobManager extends StallableJobManager
{
    /**
     * @var string
     */
    protected $jobArchiveClass;

    /**
     * DoctrineJobManager constructor.
     *
     * @param RunManager       $runManager
     * @param JobTimingManager $jobTimingManager
     * @param $jobClass
     * @param EventDispatcher $eventDispatcher
     * @param $jobArchiveClass
     */
    public function __construct(
        RunManager $runManager,
        JobTimingManager $jobTimingManager,
        $jobClass,
        EventDispatcher $eventDispatcher,
        $jobArchiveClass
    ) {
        $this->jobArchiveClass = $jobArchiveClass;
        parent::__construct($runManager, $jobTimingManager, $jobClass, $eventDispatcher);
    }

    /**
     * @return string
     */
    public function getJobArchiveClass()
    {
        return $this->jobArchiveClass;
    }
}
