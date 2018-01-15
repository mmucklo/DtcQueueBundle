<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Dtc\QueueBundle\Manager\ArchivableJobManager;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Manager\RunManager;

abstract class BaseDoctrineJobManager extends ArchivableJobManager
{
    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * DoctrineJobManager constructor.
     *
     * @param RunManager       $runManager
     * @param JobTimingManager $jobTimingManager
     * @param ObjectManager    $objectManager
     * @param $jobClass
     * @param $jobArchiveClass
     */
    public function __construct(
        RunManager $runManager,
        JobTimingManager $jobTimingManager,
        ObjectManager $objectManager,
        $jobClass,
        $jobArchiveClass
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($runManager, $jobTimingManager, $jobClass, $jobArchiveClass);
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * @return ObjectRepository
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
        $objectManager = $this->getObjectManager();
        $objectManager->remove($job);
        $objectManager->flush();
    }
}
