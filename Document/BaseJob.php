<?php

namespace Dtc\QueueBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\GridBundle\Annotation as Grid;
use Dtc\QueueBundle\Doctrine\BaseJob as BaseDoctrineJob;

abstract class BaseJob extends BaseDoctrineJob
{
    /**
     * @Grid\Column(order=1,sortable=true,searchable=true)
     * @ODM\Id
     */
    protected $id;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ODM\Field(type="string", name="worker_name")
     */
    protected $workerName;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ODM\Field(type="string", name="class_name")
     */
    protected $className;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ODM\Field(type="string")
     */
    protected $method;

    /**
     * @Grid\Column(sortable=true,searchable=true)
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
     * @Grid\Column(sortable=true,searchable=true)
     * @ODM\Field(type="int", nullable=true)
     * @ODM\Index(unique=false, order="asc")
     */
    protected $priority;

    /**
     * @ODM\Field(type="string")
     */
    protected $crcHash;

    /**
     * @Grid\Column(sortable=true, order=2)
     * @ODM\Field(type="date", nullable=true)
     * @ODM\AlsoLoad(name="when")
     * @ODM\Index(unique=false, order="asc")
     */
    protected $whenAt;

    /**
     * @Grid\Column(sortable=true)
     * @ODM\Field(type="date", nullable=true)
     */
    protected $expiresAt;

    /**
     * When the job started.
     *
     * @Grid\Column(sortable=true)
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
     * @ODM\AlsoLoad(name="stalledCount")
     * @ODM\Field(type="int")
     */
    protected $stalls = 0;

    /**
     * @ODM\AlsoLoad(name="maxStalled")
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
     * @ODM\AlsoLoad(name="errorCount")
     * @ODM\Field(type="int")
     */
    protected $exceptions = 0;

    /**
     * @ODM\AlsoLoad(name="maxError")
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
