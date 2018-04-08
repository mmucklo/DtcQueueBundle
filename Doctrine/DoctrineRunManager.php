<?php

namespace Dtc\QueueBundle\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Dtc\QueueBundle\Model\Run;
use Dtc\QueueBundle\Manager\RunManager;

abstract class DoctrineRunManager extends RunManager
{
    use ProgressCallbackTrait;

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
        return $this->getObjectManager()->getRepository($this->getRunClass());
    }

    /**
     * @return null|string
     */
    public function getRunArchiveClass()
    {
        return $this->runArchiveClass;
    }

    public function pruneStalledRuns(callable $progressCallback = null)
    {
        $total = $this->countOldLiveRuns();
        $this->updateProgress($progressCallback, 0, $total);
        $runs = $this->getOldLiveRuns();
        /** @var Run $run */
        $offset = 0;
        $limit = ceil($total / 20.0);;
        $processed = 0;
        $deleted = 0;
        while($runs = $this->getOldLiveRuns($offset, $limit)) {
            $delete = [];
            foreach ($runs as $run) {
                $processed++;
                $lastHeartbeat = $run->getLastHeartbeatAt();
                $time = time() - 3600;
                $processTimeout = $run->getProcessTimeout();
                $time -= $processTimeout;
                $oldDate = new \DateTime("@$time");
                if (null === $lastHeartbeat || $oldDate > $lastHeartbeat) {
                    $delete[] = $run;
                }
                else {
                    $offset++;
                }
            }
            $deleted += $this->deleteOldRuns($delete);
            $this->updateProgress($progressCallback, $processed);
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
        $this->persist($run, $action);
    }

    /**
     * @return array
     */
    abstract protected function getOldLiveRuns($offset, $limit);
    abstract protected function countOldLiveRuns();

    abstract protected function persist($object, $action = 'persist');

    abstract protected function removeOlderThan($objectName, $field, \DateTime $olderThan);

    abstract public function deleteArchiveRuns(callable $progressCallback = null);

    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        return $this->removeOlderThan($this->getRunArchiveClass(), 'endedAt', $olderThan);
    }
}
