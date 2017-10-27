<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\RunManager;

abstract class BaseRunManager extends RunManager
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var string|null */
    protected $runArchiveClass;

    /**
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    /**
     * @param ObjectManager $objectManager
     */
    public function setObjectManager(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return ObjectRepository
     */
    public function getRepository()
    {
        return $this->objectManager->getRepository($this->getRunClass());
    }

    /**
     * @return null|string
     */
    public function getRunArchiveClass()
    {
        return $this->runArchiveClass;
    }

    /**
     * @param null|string $runArchiveClass
     */
    public function setRunArchiveClass($runArchiveClass)
    {
        $this->runArchiveClass = $runArchiveClass;
    }

    public function recordJobRun(Job $job)
    {
        parent::recordJobRun($job);

        $finishedAt = $job->getFinishedAt();
        if (null === $finishedAt) {
            $finishedAt = new \DateTime();
        }
        $jobTiming = new $this->jobTimingClass();
        $jobTiming->setFinishedAt($finishedAt);
        $this->objectManager->persist($jobTiming);
        $this->objectManager->flush();
    }
}
