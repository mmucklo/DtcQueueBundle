<?php

namespace Dtc\QueueBundle\Documents;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\QueueBundle\Model\Job as BaseJob;

/**
 * @ODM\Document(db="queue", collection="job")
 * @ODM\Index(keys={"className"="asc"})
 */
class Job extends BaseJob
{
    /**
     * @ODM\Id
     */
    protected $id;

    /**
     * @ODM\Field(type="string", name="worker_name")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $workerName;

    /**
     * @ODM\Field(type="string", name="class_name")
     */
    protected $className;

    /**
     * @ODM\Field(type="string")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $method;

    /**
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
     * @ODM\Field(type="boolean", nullable=true)
     */
    protected $locked;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $lockedAt;

    /**
     * @ODM\Field(type="int", nullable=true)
     * @ODM\Index(unique=false, order="asc")
     */
    protected $priority;

    /**
     * @ODM\Field(type="string")
     */
    protected $crcHash;

    /**
     * @ODM\Field(type="date", nullable=true)
     * @ODM\Index(unique=false, order="asc")
     */
    protected $whenAt;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $expiresAt;

    /**
     * When the job started.
     *
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
