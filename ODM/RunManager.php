<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\QueueBundle\Doctrine\BaseRunManager;

class RunManager extends BaseRunManager
{
    use CommonTrait;

    protected function getOldLiveRuns()
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        /** @var Builder $queryBuilder */
        $queryBuilder = $objectManager->createQueryBuilder($this->getRunClass());
        $queryBuilder->find();
        $time = time() - 86400;
        $date = new \DateTime("@$time");
        $queryBuilder->field('lastHeartbeatAt')->lt($date);

        return $queryBuilder->getQuery()->toArray();
    }
}
