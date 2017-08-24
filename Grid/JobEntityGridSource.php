<?php

namespace Dtc\QueueBundle\Grid;

use Dtc\QueueBundle\ORM\JobManager;
use Dtc\GridBundle\Grid\Source\EntityGridSource;

class JobEntityGridSource extends EntityGridSource
{
    public function __construct(JobManager $manager)
    {
        parent::__construct($manager->getEntityManager(), $manager->getEntityName());
    }
}
