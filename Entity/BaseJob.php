<?php

namespace Dtc\QueueBundle\Entity;

use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Doctrine\ORM\Mapping as ORM;
use Dtc\GridBundle\Annotation as Grid;
use Dtc\QueueBundle\Model\MicrotimeTrait;
use Dtc\QueueBundle\Model\StallableJob;

/**
 * Class BaseJob.
 *
 * @IgnoreAnnotation("Dtc\GridBundle\Annotation\Grid")
 */
abstract class BaseJob extends StallableJob
{
    use MicrotimeTrait;
    /**
     * @Grid\Column(order=1,sortable=true,searchable=true)
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(name="worker_name", type="string")
     */
    protected $workerName;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(name="class_name", type="string")
     */
    protected $className;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(name="method", type="string")
     */
    protected $method;

    /**
     * @Grid\Column(sortable=true, searchable=true)
     * @ORM\Column(name="status", type="string")
     */
    protected $status;

    /**
     * @ORM\Column(name="args", type="text")
     */
    protected $args;

    /**
     * @Grid\Column(sortable=true,searchable=true)
     * @ORM\Column(name="priority", type="integer", nullable=true)
     */
    protected $priority;

    /**
     * @ORM\Column(name="crc_hash", type="string")
     */
    protected $crcHash;

    /**
     * whenAt in Microseconds.
     *
     * @Grid\Column(sortable=true,order=2)
     * @ORM\Column(name="when_us", type="decimal", precision=18, scale=0, nullable=true)
     */
    protected $whenUs;

    /**
     * @Grid\Column(sortable=true)
     * @ORM\Column(name="expires_at", type="datetime", nullable=true)
     */
    protected $expiresAt;

    /**
     * When the job started.
     *
     * @Grid\Column(sortable=true)
     * @ORM\Column(name="started_at", type="datetime", nullable=true)
     */
    protected $startedAt;

    /**
     * When the job finished.
     *
     * @ORM\Column(name="finished_at", type="datetime", nullable=true)
     */
    protected $finishedAt;

    /**
     * @ORM\Column(name="elapsed", type="float", nullable=true)
     */
    protected $elapsed;

    /**
     * @ORM\Column(name="message", type="text", nullable=true)
     */
    protected $message;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime")
     */
    protected $updatedAt;

    /**
     * @ORM\Column(name="max_duration", type="integer", nullable=true)
     */
    protected $maxDuration;

    /**
     * @ORM\Column(name="run_id", type="bigint", nullable=true)
     */
    protected $runId;

    /**
     * @ORM\Column(name="stalls", type="integer")
     */
    protected $stalls = 0;

    /**
     * @ORM\Column(name="max_stalls", type="integer", nullable=true)
     */
    protected $maxStalls;

    /**
     * @ORM\Column(name="exceptions", type="integer")
     */
    protected $exceptions = 0;

    /**
     * @ORM\Column(name="max_exceptions", type="integer", nullable=true)
     */
    protected $maxExceptions;

    /**
     * @ORM\Column(name="failures", type="integer")
     */
    protected $failures = 0;

    /**
     * @ORM\Column(name="max_failures", type="integer", nullable=true)
     */
    protected $maxFailures;

    /**
     * @ORM\Column(name="retries", type="integer")
     */
    protected $retries = 0;

    /**
     * @ORM\Column(name="max_retries", type="integer", nullable=true)
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
