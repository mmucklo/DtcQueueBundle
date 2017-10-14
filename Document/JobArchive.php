<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation as Grid;

/**
 * @Grid\Grid(actions={@Grid\ShowAction()})
 * @ODM\Document(db="dtc_queue", collection="job_archive")
 */
class JobArchive extends BaseJob
{
    /**
     * @ODM\Field(type="date")
     * @ODM\Index(unique=false)
     */
    protected $updatedAt;

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
     * @Grid\Column()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $finishedAt;

    /**
     * When the job finished.
     *
     * @ODM\Field(type="date", nullable=true)
     */
    protected $startedAt;

    /**
     * @ODM\Field(type="boolean", nullable=true)
     */
    protected $locked;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $lockedAt;

    /**
     * @Grid\Column()
     * @ODM\Field(type="float")
     */
    protected $elapsed;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $expiresAt;
}
