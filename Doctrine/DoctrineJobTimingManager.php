<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Manager\JobTimingManager;

abstract class DoctrineJobTimingManager extends JobTimingManager
{
    /** @var ObjectManager */
    protected $objectManager;

    public function __construct(ObjectManager $objectManager, $jobTimingClass, $recordTimings)
    {
        $this->objectManager = $objectManager;
        parent::__construct($jobTimingClass, $recordTimings);
    }

    /**
     * @return ObjectManager
     */
    public function getObjectManager()
    {
        return $this->objectManager;
    }

    public function performRecording($status, \DateTime $finishedAt = null)
    {
        if (null === $finishedAt) {
            $finishedAt = new \DateTime();
        }

        /** @var JobTiming $jobTiming */
        $jobTiming = new $this->jobTimingClass();
        $jobTiming->setFinishedAt($finishedAt);
        $jobTiming->setStatus($status);
        $this->objectManager->persist($jobTiming);
        $this->objectManager->flush();
    }

    abstract protected function removeOlderThan($objectName, $field, \DateTime $olderThan);

    public function pruneJobTimings(\DateTime $olderThan)
    {
        return $this->removeOlderThan($this->getJobTimingClass(), 'createdAt', $olderThan);
    }
}
