<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Dtc\QueueBundle\Doctrine\DoctrineRunManager;

class RunManager extends DoctrineRunManager
{
    use CommonTrait;

    public function getObjectManager()
    {
        return $this->getObjectManagerReset();
    }

    protected function countOldLiveRuns() {
        return $this->createOldLiveRunsQueryBuilder('count(r.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @param string $select
     * @return QueryBuilder
     */
    protected function createOldLiveRunsQueryBuilder($select) {
        $time = time() - 86400;
        $date = new \DateTime("@$time");

        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $objectManager->createQueryBuilder();
        $queryBuilder->select($select)
            ->from($this->getRunClass(), 'r');
        $time = time() - 86400;
        $date = \DateTime::createFromFormat('U', strval($time), new \DateTimeZone(date_default_timezone_get()));
        $queryBuilder->where('r.lastHeartbeatAt < :date');
        $queryBuilder->setParameter(':date', $date);
        $queryBuilder->orWhere('r.lastHeartbeatAt is NULL');
        return $queryBuilder;
    }

    protected function getOldLiveRuns($offset, $limit)
    {
        return $this->createOldLiveRunsQueryBuilder('r')->setMaxResults($limit)->setFirstResult($offset)->getQuery()->getResut();
    }

}
