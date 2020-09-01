<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\QueueBundle\Document\Job;
use Dtc\QueueBundle\Document\JobTiming;
use Dtc\QueueBundle\Document\Run;
use MongoDB\DeleteResult;

trait CommonTrait
{
    /**
     * @param string $objectName
     * @param string $field
     *
     * @return int
     */
    protected function removeOlderThan($objectName, $field, \DateTime $olderThan)
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        $qb = $objectManager->createQueryBuilder($objectName);
        $qb
            ->remove()
            ->field($field)->lt($olderThan);

        $query = $qb->getQuery();
        $result = $query->execute();
        if ($result instanceof DeleteResult) {
            return $result->getDeletedCount();
        } elseif (isset($result['n'])) {
            return $result['n'];
        }

        return 0;
    }

    /**
     * @param Run|Job|JobTiming $object
     * @param string            $action
     */
    protected function persist($object, $action = 'persist')
    {
        $objectManager = $this->getObjectManager();
        $objectManager->$action($object);
        $objectManager->flush();
    }

    /**
     * @return ObjectManager
     */
    abstract public function getObjectManager();

    /**
     * @param string $objectName
     */
    public function stopIdGenerator($objectName)
    {
        // Not needed for ODM
    }

    public function restoreIdGenerator($objectName)
    {
        // Not needed for ODM
    }
}
