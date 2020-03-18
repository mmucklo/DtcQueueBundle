<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\QueueBundle\Doctrine\DoctrineRunManager;

class RunManager extends DoctrineRunManager
{
    use CommonTrait;

    /**
     * @return array
     *
     * @throws \Exception
     */
    protected function getOldLiveRuns()
    {
        /** @var DocumentManager $objectManager */
        $objectManager = $this->getObjectManager();
        /** @var Builder $queryBuilder */
        $queryBuilder = $objectManager->createQueryBuilder($this->getRunClass());
        $queryBuilder->find();
        $time = time() - 86400;
        $date = \DateTime::createFromFormat('U', strval($time));
        if (false === $date) {
            throw new \Exception("Could not create DateTime object from $time");
        }
        $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $queryBuilder->field('lastHeartbeatAt')->lt($date);

        return $queryBuilder->getQuery()->toArray();
    }
}
