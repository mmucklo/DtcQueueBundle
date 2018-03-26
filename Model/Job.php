<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Manager\JobManagerInterface;
use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;

class Job extends BaseJob
{
    const STATUS_EXPIRED = 'expired';

    protected $id;
    protected $message;
    protected $crcHash;
    protected $expiresAt;
    protected $delay;
    protected $startedAt;
    protected $maxDuration;
    protected $runId;
    protected $finishedAt;
    protected $elapsed;

    /** @var  EventDispatcher $eventDispatcher */
    protected $eventDispatcher;

    public function __call($method, $args)
    {
        $this->method = $method;
        $this->setArgs($args);

        // Make sure the method exists - job should not be created
        if (!method_exists($this->worker, $method)) {
            throw new \BadMethodCallException("{$this->className}->{$method}() does not exist");
        }

        $job = $this->jobManager->save($this);
        $event = new Event($job);
        $this->eventDispatcher->dispatch(Event::CREATE_JOB, $event);
        return $job;
    }

    public function setEventDispatcher(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return string|null
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * @param \DateTime $expiresAt
     */
    public function setExpiresAt(\DateTime $expiresAt)
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getCrcHash()
    {
        return $this->crcHash;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @param \DateTime|null $startedAt
     */
    public function setStartedAt(\DateTime $startedAt = null)
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxDuration()
    {
        return $this->maxDuration;
    }

    /**
     * @param int|null $maxDuration
     */
    public function setMaxDuration($maxDuration)
    {
        $this->maxDuration = $maxDuration;

        return $this;
    }

    /**
     * @param string $crcHash
     */
    public function setCrcHash($crcHash)
    {
        $this->crcHash = $crcHash;

        return $this;
    }

    /**
     * @param JobManagerInterface $jobManager
     */
    public function setJobManager(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;

        return $this;
    }

    /**
     * @return int
     */
    public function getDelay()
    {
        return $this->delay;
    }

    /**
     * @param int $delay Delay in seconds
     */
    public function setDelay($delay)
    {
        $this->delay = $delay;

        return $this;
    }

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

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getFinishedAt()
    {
        return $this->finishedAt;
    }

    /**
     * @param \DateTime|null $finishedAt
     */
    public function setFinishedAt($finishedAt)
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    /**
     * @return int
     */
    public function getElapsed()
    {
        return $this->elapsed;
    }

    /**
     * @param int $elapsed
     */
    public function setElapsed($elapsed)
    {
        $this->elapsed = $elapsed;

        return $this;
    }
}
