<?php

namespace Dtc\QueueBundle\Doctrine;

use Dtc\QueueBundle\Manager\ArchivableJobManager;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\RunManager;

abstract class BaseDoctrineJobManager extends ArchivableJobManager
{
    /** Number of jobs to prune / reset / gather at a time */
    public const FETCH_COUNT_MIN = 100;
    public const FETCH_COUNT_MAX = 500;
    public const SAVE_COUNT_MIN = 10;
    public const SAVE_COUNT_MAX = 100;

    /**
     * @var \Doctrine\Persistence\ObjectManager
     */
    protected $objectManager;

    /**
     * DoctrineJobManager constructor.
     *
     * @param $jobClass
     * @param $jobArchiveClass
     */
    public function __construct(
        RunManager $runManager,
        JobTimingManager $jobTimingManager,
        $objectManager,
        $jobClass,
        $jobArchiveClass
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($runManager, $jobTimingManager, $jobClass, $jobArchiveClass);
    }

    protected function getFetchCount($totalCount)
    {
        $fetchCount = intval($totalCount / 10);
        if ($fetchCount < self::FETCH_COUNT_MIN) {
            $fetchCount = self::FETCH_COUNT_MIN;
        }
        if ($fetchCount > self::FETCH_COUNT_MAX) {
            $fetchCount = self::FETCH_COUNT_MAX;
        }

        return $fetchCount;
    }

    protected function getSaveCount($totalCount)
    {
        $saveCount = intval($totalCount / 10);
        if ($saveCount < self::SAVE_COUNT_MIN) {
            $saveCount = self::SAVE_COUNT_MIN;
        }
        if ($saveCount > self::SAVE_COUNT_MAX) {
            $saveCount = self::SAVE_COUNT_MAX;
        }

        return $saveCount;
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * @return \Doctrine\Persistence\ObjectRepository
     */
    public function getRepository()
    {
        return $this->getObjectManager()->getRepository($this->getJobClass());
    }

    protected function flush()
    {
        $this->getObjectManager()->flush();
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        $this->persist($job, 'remove');
    }

    abstract protected function persist($object, $action = 'persist');

    protected function addWorkerNameMethod(array &$criterion, $workerName = null, $method = null)
    {
        if (null !== $workerName) {
            $criterion['workerName'] = $workerName;
        }
        if (null !== $method) {
            $criterion['method'] = $method;
        }
    }
}
