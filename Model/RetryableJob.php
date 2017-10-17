<?php

namespace Dtc\QueueBundle\Model;

abstract class RetryableJob extends \Dtc\QueueBundle\Model\Job
{
    const STATUS_MAX_ERROR = 'max_error';
    const STATUS_MAX_STALLED = 'max_stallled';
    const STATUS_MAX_RETRIES = 'max_retries';

    protected $maxStalled;
    protected $stalledCount = 0;
    protected $maxError;
    protected $errorCount = 0;
    protected $maxRetries;
    protected $retries = 0;
    protected $createdAt;
    protected $updatedAt;

    /**
     * @return int|null
     */
    public function getMaxStalled()
    {
        return $this->maxStalled;
    }

    /**
     * @param int|null $maxStalled
     *
     * @return RetryableJob
     */
    public function setMaxStalled($maxStalled)
    {
        $this->maxStalled = $maxStalled;

        return $this;
    }

    /**
     * @return int
     */
    public function getStalledCount()
    {
        return $this->stalledCount;
    }

    /**
     * @param int $stalledCount
     *
     * @return RetryableJob
     */
    public function setStalledCount($stalledCount)
    {
        $this->stalledCount = $stalledCount;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxError()
    {
        return $this->maxError;
    }

    /**
     * @param int|null $maxError
     *
     * @return RetryableJob
     */
    public function setMaxError($maxError)
    {
        $this->maxError = $maxError;

        return $this;
    }

    /**
     * @return int
     */
    public function getErrorCount()
    {
        return $this->errorCount;
    }

    /**
     * @param int $erroredCount
     *
     * @return RetryableJob
     */
    public function setErrorCount($errorCount)
    {
        $this->errorCount = $errorCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getRetries()
    {
        return $this->retries;
    }

    /**
     * @param int $retries
     *
     * @return RetryableJob
     */
    public function setRetries($retries)
    {
        $this->retries = $retries;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    /**
     * @param int|null $maxRetries
     *
     * @return RetryableJob
     */
    public function setMaxRetries($maxRetries)
    {
        $this->maxRetries = $maxRetries;

        return $this;
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
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }
}
