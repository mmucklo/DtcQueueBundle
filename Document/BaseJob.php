<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation\GridColumn;
use Dtc\QueueBundle\Model\Job;

abstract class BaseJob extends Job
{
    /**
     * @GridColumn()
     * @ODM\Id
     */
    protected $id;

    /**
     * @GridColumn()
     * @ODM\Field(type="string", name="worker_name")
     */
    protected $workerName;

    /**
     * @GridColumn()
     * @ODM\Field(type="string", name="class_name")
     */
    protected $className;

    /**
     * @GridColumn()
     * @ODM\Field(type="string")
     */
    protected $method;

    /**
     * @GridColumn()
     * @ODM\Field(type="string")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $status;

    /**
     * @ODM\Field(type="hash")
     */
    protected $args;

    /**
     * @ODM\Field(type="boolean", nullable=true)
     */
    protected $batch;

    /**
     * @GridColumn()
     * @ODM\Field(type="boolean", nullable=true)
     */
    protected $locked;

    /**
     * @GridColumn()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $lockedAt;

    /**
     * @GridColumn()
     * @ODM\Field(type="int", nullable=true)
     * @ODM\Index(unique=false, order="asc")
     */
    protected $priority;

    /**
     * @ODM\Field(type="string")
     */
    protected $crcHash;

    /**
     * @GridColumn()
     * @ODM\Field(type="date", nullable=true)
     * @ODM\Index(unique=false, order="asc")
     */
    protected $whenAt;

    /**
     * @GridColumn()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $expiresAt;

    /**
     * When the job started.
     *
     * @GridColumn()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $startedAt;

    /**
     * When the job finished.
     *
     * @ODM\Field(type="date", nullable=true)
     */
    protected $finishedAt;

    /**
     * @ODM\Field(type="float")
     */
    protected $elapsed;

    /**
     * @ODM\Field(type="string", nullable=true)
     */
    protected $message;

    /**
     * @ODM\Field(type="date")
     */
    protected $createdAt;

    /**
     * @ODM\Field(type="date")
     */
    protected $updatedAt;

    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxDuration;
}
