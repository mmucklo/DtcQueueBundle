<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\QueueBundle\Model\StallableJob;

abstract class BaseJob extends StallableJob
{
    /**
     * @ODM\Id
     */
    protected $id;

    /**
     * @ODM\Field(type="string", name="worker_name")
     */
    protected $workerName;

    /**
     * @ODM\Field(type="string", name="class_name")
     */
    protected $className;

    /**
     * @ODM\Field(type="string")
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
     * @ODM\AlsoLoad("when")
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
     * @ODM\AlsoLoad("stalledCount")
     * @ODM\Field(type="int")
     */
    protected $stalls = 0;

    /**
     * @ODM\AlsoLoad("maxStalled")
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxStalls;

    /**
     * @ODM\Field(type="int")
     */
    protected $failures = 0;

    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxFailures;

    /**
     * @ODM\AlsoLoad("errorCount")
     * @ODM\Field(type="int")
     */
    protected $exceptions = 0;

    /**
     * @ODM\AlsoLoad("maxError")
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxExceptions;

    /**
     * @ODM\Field(type="int")
     */
    protected $retries = 0;

    /**
     * @ODM\Field(type="int", nullable=true)
     */
    protected $maxRetries;
}
