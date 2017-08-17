<?php

namespace Dtc\QueueBundle\Grid;

use Dtc\QueueBundle\Documents\JobManager;
use Dtc\GridBundle\Grid\Source\DocumentGridSource;

class JobGridSource extends DocumentGridSource
{
    public function __construct(JobManager $manager)
    {
        parent::__construct($manager->getDocumentManager(), $manager->getDocumentName());
    }
}
