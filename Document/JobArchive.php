<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @Grid\Grid(actions={@Grid\ShowAction()},sort=@Grid\Sort(column="updatedAt",direction="DESC"))
 * @ODM\Document(db="dtc_queue", collection="job_archive")
 */
class JobArchive extends BaseJob
{
    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $priority;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $whenAt;

    /**
     * When the job finished.
     *
     * @Grid\Column(order=2, sortable=true)
     * @ODM\Field(type="date", nullable=true)
     * @ODM\Index(unique=false, order="desc")
     */
    protected $finishedAt;

    /**
     * @Grid\Column()
     * @ODM\Field(type="float")
     */
    protected $elapsed;

    /**
     * When the job finished.
     *
     * @ODM\Field(type="date", nullable=true)
     */
    protected $startedAt;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $expiresAt;

    /**
     * @Grid\Column(sortable=true, order=3)
     * @ODM\Field(type="date")
     * @ODM\Index(unique=false)
     */
    protected $updatedAt;
}
