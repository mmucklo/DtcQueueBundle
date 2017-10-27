<?php

namespace Dtc\QueueBundle\ODM;

trait CommonTrait
{
    /**
     * @param \Doctrine\ODM\MongoDB\DocumentManager $documentManager
     * @param string                                $objectName
     * @param string                                $field
     * @param \DateTime                             $olderThan
     *
     * @return int
     */
    protected function removeOlderThan(\Doctrine\ODM\MongoDB\DocumentManager $documentManager, $objectName, $field, \DateTime $olderThan)
    {
        $qb = $documentManager->createQueryBuilder($objectName);
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
}
