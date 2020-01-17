<?php

namespace Dtc\QueueBundle\Model;

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

    public function __call($method, $args)
    {
        $this->method = $method;
        $this->setArgs($args);

        // Make sure the method exists - job should not be created
        if (!is_callable([$this->worker, $method])) {
            throw new \BadMethodCallException("{$this->className}->{$method}() does not exist");
        }

        $job = $this->jobManager->save($this);

        return $job;
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
     * @param float|null $elapsed
     */
    public function setElapsed($elapsed)
    {
        $this->elapsed = $elapsed;

        return $this;
    }
}
