<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Model\RunManager;

abstract class BaseRunManager extends RunManager
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var string|null */
    protected $runArchiveClass;

    public function __construct(ObjectManager $objectManager, $runClass, $jobTimingClass, $recordTimings)
    {
        $this->objectManager = $objectManager;
        parent::__construct($runClass, $jobTimingClass, $recordTimings);
    }

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
        /** @var JobTiming $jobTiming */
        $jobTiming = new $this->jobTimingClass();
        $jobTiming->setFinishedAt($finishedAt);
        $this->objectManager->persist($jobTiming);
        $this->objectManager->flush();
    }

    public function pruneStalledRuns()
    {
        $runs = $this->getOldLiveRuns();
        /** @var Run $run */
        $delete = [];

        foreach ($runs as $run) {
            $lastHeartbeat = $run->getLastHeartbeatAt();
            $time = time() - 3600;
            $processTimeout = $run->getProcessTimeout();
            $time -= $processTimeout;
            $oldDate = new \DateTime("@$time");
            if (null === $lastHeartbeat || $oldDate > $lastHeartbeat) {
                $delete[] = $run;
            }
        }

        return $this->deleteOldRuns($delete);
    }

    /**
     * @param array $delete
     *
     * @return int
     */
    protected function deleteOldRuns(array $delete)
    {
        $count = count($delete);
        $objectManager = $this->getObjectManager();
        for ($i = 0; $i < $count; $i += 100) {
            $deleteList = array_slice($delete, $i, 100);
            foreach ($deleteList as $object) {
                $objectManager->remove($object);
            }
            $this->flush();
        }

        return $count;
    }

    protected function flush()
    {
        $this->getObjectManager()->flush();
    }

    protected function persistRun(Run $run, $action = 'persist')
    {
        $objectManager = $this->getObjectManager();
        $objectManager->$action($run);
        $objectManager->flush();
    }

    /**
     * @return array
     */
    abstract protected function getOldLiveRuns();

    abstract protected function removeOlderThan($objectName, $field, \DateTime $olderThan);

    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        return $this->removeOlderThan($this->getRunArchiveClass(), 'endedAt', $olderThan);
    }

    public function pruneJobTimings(\DateTime $olderThan)
    {
        return $this->removeOlderThan($this->getJobTimingClass(), 'createdAt', $olderThan);
    }
}
