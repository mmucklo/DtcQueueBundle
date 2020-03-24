<?php

namespace Dtc\QueueBundle\ORM;

use Doctrine\ORM\EntityManager;
use Dtc\GridBundle\Grid\Column\GridColumn;
use Dtc\GridBundle\Grid\Source\ColumnExtractionTrait;
use Dtc\GridBundle\Grid\Source\EntityGridSource;
use Dtc\QueueBundle\Model\BaseJob;

class LiveJobsGridSource extends EntityGridSource
{
    protected $jobManager;
    protected $running = false;
    private $columnSource;

    use ColumnExtractionTrait;

    public function getId()
    {
        return 'dtc_queue.grid_source.jobs_'.($this->isRunning() ? 'running' : 'waiting').'.orm';
    }

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;
        /** @var EntityManager $entityManager */
        $entityManager = $jobManager->getObjectManager();
        parent::__construct($entityManager, $jobManager->getJobClass());
    }

    /**
     * @param bool $flag
     */
    public function setRunning($flag)
    {
        $this->running = $flag;
    }

    public function isRunning()
    {
        return $this->running;
    }

    public function setColumnSource(\Dtc\GridBundle\Grid\Source\ColumnSource $columnSource)
    {
        $this->columnSource = $columnSource;
    }

    protected function getRunningQueryBuilder()
    {
        /** @var EntityManager $entityManager */
        $entityManager = $this->jobManager->getObjectManager();
        $queryBuilder = $entityManager->createQueryBuilder()->add('select', 'j');
        $queryBuilder->from($this->jobManager->getJobClass(), 'j');
        $queryBuilder->where('j.status = :status')->setParameter(':status', BaseJob::STATUS_RUNNING);
        $queryBuilder->orderBy('j.startedAt', 'DESC');
        $queryBuilder->setFirstResult($this->offset)
                        ->setMaxResults($this->limit);

        return $queryBuilder;
    }

    protected function getQueryBuilder()
    {
        if ($this->isRunning()) {
            return $this->getRunningQueryBuilder();
        }

        $queryBuilder = $this->jobManager->getJobQueryBuilder();
        $queryBuilder->add('select', 'j');
        $queryBuilder->setFirstResult($this->offset)
                        ->setMaxResults($this->limit);

        return $queryBuilder;
    }

    public function setColumns($columns)
    {
        if ($columns) {
            foreach ($columns as $column) {
                if ($column instanceof GridColumn) {
                    $column->setOption('sortable', false);
                }
            }
        }

        parent::setColumns($columns);
    }

    public function getColumns()
    {
        if ($columns = parent::getColumns()) {
            return $columns;
        }

        if (!$this->columnSource) {
            $this->autoDiscoverColumns();

            return parent::getColumns();
        }

        $columnSourceInfo = $this->columnSource->getColumnSourceInfo($this->objectManager, 'Dtc\QueueBundle\Entity\Job', false);
        $this->setColumns($columnSourceInfo->columns);
        $this->setDefaultSort($columnSourceInfo->sort);
        $this->setIdColumn($columnSourceInfo->idColumn);

        return parent::getColumns();
    }

    public function getDefaultSort()
    {
        return null;
    }

    public function getCount()
    {
        $qb = $this->getQueryBuilder();
        $qb->resetDQLPart('orderBy');
        $qb->add('select', 'count(j)')
            ->setFirstResult(null)
            ->setMaxResults(null);

        return $qb->getQuery()
            ->getSingleScalarResult();
    }
}
