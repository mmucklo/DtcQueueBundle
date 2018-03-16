<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\GridBundle\Annotation as Grid;
use Doctrine\ORM\Mapping as ORM;
use Dtc\QueueBundle\Model\MicrotimeTrait;
use Dtc\QueueBundle\Doctrine\BaseJob as BaseDoctrineJob;

abstract class BaseJob extends BaseDoctrineJob
{
    use MicrotimeTrait;
    /**
     * @Grid\Column(order=1,sortable=true,searchable=true)
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
     * @Grid\Column(sortable=true,searchable=true)
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $priority;

    /**
     * @ORM\Column(type="string")
     */
    protected $crcHash;

    /**
     * whenAt in Microseconds.
     *
     * @Grid\Column(sortable=true,order=2)
     * @ORM\Column(type="decimal", precision=18, scale=0, nullable=true)
     */
    protected $whenUs;

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
     * @ORM\Column(type="integer")
     */
    protected $stalls = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxStalls;

    /**
     * @ORM\Column(type="integer")
     */
    protected $exceptions = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxExceptions;

    /**
     * @ORM\Column(type="integer")
     */
    protected $failures = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxFailures;

    /**
     * @ORM\Column(type="integer")
     */
    protected $retries = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $maxRetries;

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
