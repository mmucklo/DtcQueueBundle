<?php

namespace Dtc\QueueBundle\Entity;

use Dtc\GridBundle\Annotation\GridColumn;
use Dtc\QueueBundle\Model\Job;
use Doctrine\ORM\Mapping as ORM;

abstract class BaseJob extends Job
{
    /**
     * @GridColumn()
     * @ORM\Column(type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @GridColumn()
     * @ORM\Column(type="string")
     */
    protected $workerName;

    /**
     * @GridColumn()
     * @ORM\Column(type="string")
     */
    protected $className;

    /**
     * @GridColumn()
     * @ORM\Column(type="string")
     */
    protected $method;

    /**
     * @GridColumn()
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
     * @GridColumn()
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $locked;

    /**
     * @GridColumn()
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lockedAt;

    /**
     * @GridColumn()
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $priority;

    /**
     * @ORM\Column(type="string")
     */
    protected $crcHash;

    /**
     * @GridColumn()
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $whenAt;

    /**
     * @GridColumn()
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $expiresAt;

    /**
     * When the job started.
     *
     * @GridColumn()
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
