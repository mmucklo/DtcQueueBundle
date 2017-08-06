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
     * @ODM\Field(type="boolean")
     */
    protected $batch;

    /**
     * @ODM\Field(type="boolean")
     */
    protected $locked;

    /**
     * @ODM\Field(type="date")
     */
    protected $lockedAt;

    /**
     * @ODM\Field(type="int")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $priority;

    /**
     * @ODM\Field(type="string")
     */
    protected $crcHash;

    /**
     * @ODM\Field(type="date")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $when;

    /**
     * When the job started.
     *
     * @ODM\Field(type="date")
     */
    protected $startedAt;

    /**
     * When the job finished.
     *
     * @ODM\Field(type="date")
     */
    protected $finishedAt;

    /**
     * @ODM\Field(type="float")
     */
    protected $elapsed;

    /**
     * @ODM\Field(type="string")
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
}
