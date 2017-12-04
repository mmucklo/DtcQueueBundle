<?php

namespace Dtc\QueueBundle\ODM;

use Doctrine\ODM\MongoDB\DocumentManager;
use Dtc\GridBundle\Grid\Source\DocumentGridSource;

class LiveJobGridSource extends DocumentGridSource
{
    protected $jobManager;

    public function getId()
    {
        return 'dtc_queue.grid_source.live_jobs.odm';
    }

    public function __construct(JobManager $jobManager)
    {
        $this->jobManager = $jobManager;

        /** @var DocumentManager $documentManager */
        $documentManager = $jobManager->getObjectManager();
        parent::__construct($documentManager, $jobManager->getObjectName());
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
        $builder = $this->jobManager->getJobQueryBuilder();
        $builder->limit($this->limit);
        $builder->skip($this->offset);

        return $builder;
    }
}
