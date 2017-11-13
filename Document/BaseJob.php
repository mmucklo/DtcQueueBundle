<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation as Grid;

abstract class BaseJob extends \Dtc\QueueBundle\Model\RetryableJob
{
    /**
     * @Grid\Column()
     * @ODM\Id
     */
    protected $id;

    /**
     * @Grid\Column()
     * @ODM\Field(type="string", name="worker_name")
     */
    protected $workerName;

    /**
     * @Grid\Column()
     * @ODM\Field(type="string", name="class_name")
     */
    protected $className;

    /**
     * @Grid\Column()
     * @ODM\Field(type="string")
     */
    protected $method;

    /**
     * @Grid\Column()
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
     * @Grid\Column()
     * @ODM\Field(type="boolean", nullable=true)
     */
    protected $locked;

    /**
     * @ODM\Field(type="date", nullable=true)
     */
    protected $lockedAt;

    /**
     * @Grid\Column()
     * @ODM\Field(type="int", nullable=true)
     * @ODM\Index(unique=false, order="asc")
     */
    protected $priority;

    /**
     * @ODM\Field(type="string")
     */
    protected $crcHash;

    /**
     * @Grid\Column()
     * @ODM\Field(type="date", nullable=true)
     * @ODM\AlsoLoad(name="when")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $whenAt;

    /**
     * @Grid\Column()
     * @ODM\Field(type="date", nullable=true)
     */
    protected $expiresAt;

    /**
     * When the job started.
     *
     * @Grid\Column()
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
     * @ODM\Field(type="float", nullable=true)
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

    /**
     * @ODM\Field(type="object_id", nullable=true)
     */
    protected $runId;

    /**
     * @ODM\Field(type="int")
     */
    protected $stalledCount = 0;

    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxStalled;

    /**
     * @ODM\Field(type="int")
     */
    protected $errorCount = 0;

    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxError;

    /**
     * @ODM\Field(type="int")
     */
    protected $retries = 0;

    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxRetries;
}
