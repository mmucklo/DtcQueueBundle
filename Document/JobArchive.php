<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
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
     * @ODM\Field(type="date", nullable=true)
     * @ODM\Index(unique=false, order="desc")
     */
    protected $finishedAt;

    /**
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
     * @ODM\Field(type="date")
     * @ODM\Index(unique=false)
     */
    protected $updatedAt;
}
