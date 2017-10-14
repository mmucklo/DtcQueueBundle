<?php

namespace Dtc\QueueBundle\Model;

class Job extends BaseJob
{
    protected $id;
    protected $message;
    protected $crcHash;
    protected $locked;
    protected $lockedAt;
    protected $expiresAt;
    protected $createdAt;
    protected $updatedAt;
    protected $delay;
    protected $startedAt;
    protected $finishedAt;
    protected $maxDuration;
    protected $elapsed;
    protected $runId;

    public function __call($method, $args)
    {
        $this->method = $method;
        $this->setArgs($args);
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        // Make sure the method exists - job should not be created
        if (!method_exists($this->worker, $method)) {
            throw new \Exception("{$this->className}->{$method}() does not exist");
        }

        $this->jobManager->save($this);

        return $this;
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
    }

    /**
     * @return bool|null
     */
    public function getLocked()
    {
        return $this->locked;
    }

    /**
     * @return \DateTime|null
     */
    public function getLockedAt()
    {
        return $this->lockedAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * @param bool|null $locked
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;
    }

    /**
     * @param \DateTime|null $lockedAt
     */
    public function setLockedAt($lockedAt)
    {
        $this->lockedAt = $lockedAt;
    }

    /**
     * @param \DateTime $expiresAt
     */
    public function setExpiresAt(\DateTime $expiresAt)
    {
        $this->expiresAt = $expiresAt;
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
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return \DateTime|null
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @param \DateTime $startedAt
     */
    public function setStartedAt(\DateTime $startedAt)
    {
        $this->startedAt = $startedAt;
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
    }

    /**
     * @param string $crcHash
     */
    public function setCrcHash($crcHash)
    {
        $this->crcHash = $crcHash;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param JobManagerInterface $jobManager
     */
    public function setJobManager(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
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
    }
}
