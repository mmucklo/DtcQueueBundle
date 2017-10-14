<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\GridBundle\Annotation as Grid;
use Doctrine\ORM\Mapping as ORM;

abstract class BaseJob extends \Dtc\QueueBundle\Model\Job
{
    /**
     * @Grid\Column()
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(type="string")
     */
    protected $workerName;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(type="string")
     */
    protected $className;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(type="string")
     */
    protected $method;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(type="string")
     */
    protected $status;

    /**
     * @ORM\Column(type="text")
     */
    protected $args;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $batch;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $locked;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lockedAt;

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $priority;

    /**
     * @ORM\Column(type="string")
     */
    protected $crcHash;

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $whenAt;

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $expiresAt;

    /**
     * When the job started.
     *
     * @Grid\Column(sortable=true)
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $startedAt;

    /**
     * When the job finished.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $finishedAt;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    protected $message;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxDuration;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    protected $runId;

    /**
     * @return mixed
     */
    public function getRunId()
    {
        return $this->runId;
    }

    /**
     * @param mixed $runId
     */
    public function setRunId($runId)
    {
        $this->runId = $runId;
    }

    public function setArgs($args)
    {
        $args = serialize($args);
        parent::setArgs($args);
    }

    public function getArgs()
    {
        $args = parent::getArgs();

        return unserialize($args);
    }
}
