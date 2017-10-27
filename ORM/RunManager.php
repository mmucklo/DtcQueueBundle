<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\ORM\EntityManager;
use Dtc\QueueBundle\Doctrine\BaseRunManager;

class RunManager extends BaseRunManager
{
    use CommonTrait;

    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->getObjectManager();

        return $this->removeOlderThan($entityManager, $this->getRunArchiveClass(), 'endedAt', $olderThan);
    }
}
