<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\QueueBundle\Doctrine\DoctrineRunManager;

class RunManager extends DoctrineRunManager
{
    use CommonTrait;

    protected function countOldLiveRuns() {
        $builder = $this->createOldLiveRunsQuery();
        return $this->runQuery($builder->getQuery(), 'count', [], 0);
    }

    protected function createOldLiveRunsQuery() {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        /** @var Builder $queryBuilder */
        $queryBuilder = $objectManager->createQueryBuilder($this->getRunClass());
        $queryBuilder->find();
        $time = time() - 86400;
        $date = new \DateTime("@$time");
        $queryBuilder->field('lastHeartbeatAt')->lt($date);
    }

    protected function getOldLiveRuns($offset, $limit)
    {
        $builder = $this->createOldLiveRunsQuery();
        return $this->runQuery($builder->getQuery(), 'toArray', [], []);
    }
}
