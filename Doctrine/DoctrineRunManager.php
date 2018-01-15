<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;

abstract class DoctrineRunManager extends RunManager
{
    /** @var ObjectManager */
    protected $objectManager;

    /** @var string|null */
    protected $runArchiveClass;

    public function __construct(ObjectManager $objectManager, $runClass, $runArchiveClass)
    {
        $this->runArchiveClass = $runArchiveClass;
        $this->objectManager = $objectManager;
        parent::__construct($runClass);
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
        return $this->objectManager->getRepository($this->getRunClass());
    }

    /**
     * @return null|string
     */
    public function getRunArchiveClass()
    {
        return $this->runArchiveClass;
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

    /**
     * @param Run    $run
     * @param string $action
     */
    protected function persistRun(Run $run, $action = 'persist')
    {
        parent::persistRun($run, $action);
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
}
