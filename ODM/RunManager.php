<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\QueueBundle\Doctrine\BaseRunManager;

class RunManager extends BaseRunManager
{
    use CommonTrait;

    public function pruneArchivedRuns(\DateTime $olderThan)
    {
        /** @var DocumentManager $documentManager */
        $documentManager = $this->getObjectManager();

        return $this->removeOlderThan($documentManager, $this->getRunArchiveClass(), 'endedAt', $olderThan);
    }
}
