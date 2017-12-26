<?php

namespace Dtc\QueueBundle\Model;

abstract class BaseRetryableJob extends \Dtc\QueueBundle\Model\Job
{
    const STATUS_MAX_FAILURE = 'max_failure';
    const STATUS_MAX_RETRIES = 'max_retries';

    protected $maxFailure = 0;
    protected $failureCount = 0;
    protected $maxRetries = 0;
    protected $retries = 0;
    protected $createdAt;
    protected $updatedAt;

    /**
     * @return mixed
     */
    public function getMaxFailure()
    {
        return $this->maxFailure;
    }

    /**
     * @param mixed $maxFailure
     */
    public function setMaxFailure($maxFailure)
    {
        $this->maxFailure = $maxFailure;
        return $this;
    }

    /**
     * @return int
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * @param int $failureCount
     */
    public function setFailureCount($failureCount)
    {
        $this->failureCount = $failureCount;
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
