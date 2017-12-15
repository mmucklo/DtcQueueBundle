<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\ORM\EntityManager;
use Dtc\GridBundle\Grid\Source\EntityGridSource;

class LiveJobsGridSource extends EntityGridSource
{
    protected $jobManager;

    public function getId()
    {
        return 'dtc_queue.grid_source.live_jobs.orm';
    }

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
        /** @var EntityManager $entityManager */
        $entityManager = $jobManager->getObjectManager();
        parent::__construct($entityManager, $jobManager->getJobClass());
    }

    public function getColumns()
    {
        if ($columns = parent::getColumns()) {
            return $columns;
        }
        $this->autoDiscoverColumns();

        return parent::getColumns();
    }

    protected function getQueryBuilder()
    {
        $queryBuilder = $this->jobManager->getJobQueryBuilder();
        $queryBuilder->add('select', 'j');
        $queryBuilder->setFirstResult($this->offset)
                     ->setMaxResults($this->limit);

        return $queryBuilder;
    }

    public function getCount()
    {
        $qb = $this->getQueryBuilder();
        $qb->add('select', 'count(j)')
            ->setFirstResult(null)
            ->setMaxResults(null);

        return $qb->getQuery()
            ->getSingleScalarResult();
    }
}
