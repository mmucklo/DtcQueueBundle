<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\GridBundle\Grid\Column\GridColumn;
use Dtc\GridBundle\Grid\Source\ColumnExtractionTrait;
use Dtc\GridBundle\Grid\Source\DocumentGridSource;
use Dtc\QueueBundle\Model\BaseJob;

class LiveJobsGridSource extends DocumentGridSource
{
    protected $jobManager;
    protected $running = false;

    use ColumnExtractionTrait;

    public function getId()
    {
        return 'dtc_queue.grid_source.jobs_'.($this->isRunning() ? 'running' : 'waiting').'.odm';
    }

    public function setRunning($flag)
    {
        $this->running = $flag;
    }

    public function isRunning()
    {
        return $this->running;
    }

    protected function getRunningQueryBuilder()
    {
        /** @var DocumentManager $documentManager */
        $documentManager = $this->jobManager->getObjectManager();
        $builder = $documentManager->createQueryBuilder($this->jobManager->getJobClass());
        $builder->field('status')->equals(BaseJob::STATUS_RUNNING);
        $builder->sort('startedAt', 'desc');
        $builder->limit($this->limit);
        $builder->skip($this->offset);

        return $builder;
    }

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;

        /** @var DocumentManager $documentManager */
        $documentManager = $jobManager->getObjectManager();
        parent::__construct($documentManager, $jobManager->getJobClass());
    }

    public function getColumns()
    {
        if ($columns = parent::getColumns()) {
            return $columns;
        }
        $this->autoDiscoverColumnsWrapper();

        return parent::getColumns();
    }

    public function autoDiscoverColumnsWrapper()
    {
        $this->autoDiscoverColumns();
        if ($this->columns) {
            foreach ($this->columns as $column) {
                if ($column instanceof GridColumn) {
                    $column->setOption('sortable', false);
                }
            }
        }
    }

    public function getDefaultSort()
    {
        return null;
    }

    protected function getQueryBuilder()
    {
        if ($this->isRunning()) {
            return $this->getRunningQueryBuilder();
        }
        $builder = $this->jobManager->getJobQueryBuilder();
        $builder->limit($this->limit);
        $builder->skip($this->offset);

        return $builder;
    }
}
