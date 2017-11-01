<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ODM\MongoDB\DocumentManager;

trait CommonTrait
{
    /**
     * @param ObjectManager $objectManager
     * @param string        $objectName
     * @param string        $field
     * @param \DateTime     $olderThan
     *
     * @return int
     */
    protected function removeOlderThan(ObjectManager $objectManager, $objectName, $field, \DateTime $olderThan)
    {
        /** @var DocumentManager $objectManager */
        $qb = $objectManager->createQueryBuilder($objectName);
        $qb
            ->remove()
            ->field($field)->lt($olderThan);

        $query = $qb->getQuery();
        $result = $query->execute();
        if (isset($result['n'])) {
            return $result['n'];
        }

        return 0;
    }

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
