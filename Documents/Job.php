<?php
namespace Dtc\QueueBundle\Documents;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\QueueBundle\Model\Job as BaseJob;

/**
 * @ODM\Document(db="queue", collection="job")
 * @ODM\Index(keys={"className"="asc"})
 */
class Job
    extends BaseJob
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
     * @ODM\Field(type="hash")
     */
    protected $args;

    /**
     * @ODM\Field(type="boolean")
     */
    protected $batch;

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
     * When the job get started
     *
     * @ODM\Field(type="date")
     */
    protected $started;

    /**
     * When the job get finished
     *
     * @ODM\Field(type="date")
     */
    protected $finished;

    /**
     * When the job should be finished by
     *
     * @ODM\Field(type="date")
     */
    protected $finishedBy;

    /**
     * @ODM\Field(type="date")
     */
    protected $createdAt;

    /**
     * @ODM\Field(type="date")
     */
    protected $updatedAt;
}