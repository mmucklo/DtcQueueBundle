<?php

namespace Dtc\QueueBundle\Doctrine;

use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Model\JobTiming;

abstract class DoctrineJobTimingManager extends JobTimingManager
{
    /** @var \Doctrine\Persistence\ObjectManager */
    protected $objectManager;

    public function __construct($objectManager, $jobTimingClass, $recordTimings)
    {
        $this->objectManager = $objectManager;
        parent::__construct($jobTimingClass, $recordTimings);
    }

    /**
     * @return \Doctrine\Persistence\ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    public function performRecording($status, \DateTime $finishedAt = null)
    {
        if (null === $finishedAt) {
            $finishedAt = \Dtc\QueueBundle\Util\Util::getMicrotimeDateTime();
        }

        /** @var JobTiming $jobTiming */
        $jobTiming = new $this->jobTimingClass();
        $jobTiming->setFinishedAt($finishedAt);
        $jobTiming->setStatus($status);
        $objectManager = $this->getObjectManager();
        $objectManager->persist($jobTiming);
        $objectManager->flush();
    }

    abstract protected function removeOlderThan($objectName, $field, \DateTime $olderThan);

    abstract protected function persist($object, $action = 'persist');

    public function pruneJobTimings(\DateTime $olderThan)
    {
        return $this->removeOlderThan($this->getJobTimingClass(), 'createdAt', $olderThan);
    }
}
