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

    /**
     * @return array|mixed
     * @throws \Exception
     */
    protected function getOldLiveRuns()
    {
        /** @var EntityManager $objectManager */
        $objectManager = $this->getObjectManager();
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $objectManager->createQueryBuilder();
        $queryBuilder->select(['r'])
            ->from($this->getRunClass(), 'r');
        $time = time() - 86400;
        $date = \DateTime::createFromFormat('U', strval($time));
        if (false === $date) {
            throw new \Exception("Could not create DateTime object from $time");
        }
        $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $queryBuilder->where('r.lastHeartbeatAt < :date');
        $queryBuilder->setParameter(':date', $date);

        return $queryBuilder->getQuery()->getResult();
    }
}
