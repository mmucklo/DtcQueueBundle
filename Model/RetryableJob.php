<?php

namespace Dtc\QueueBundle\Model;

abstract class RetryableJob extends \Dtc\QueueBundle\Model\Job
{
    const STATUS_MAX_FAILURES = 'max_failures';
    const STATUS_MAX_RETRIES = 'max_retries';
    const STATUS_MAX_EXCEPTIONS = 'max_exceptions';

    protected $maxFailures = 0;
    protected $failures = 0;
    protected $maxRetries = 0;
    protected $maxExceptions = 0;
    protected $exceptions = 0;
    protected $retries = 0;
    protected $createdAt;
    protected $updatedAt;

    /**
     * @return mixed
     */
    public function getMaxFailures()
    {
        return $this->maxFailures;
    }

    /**
     * @param mixed $maxFailure
     */
    public function setMaxFailures($maxFailures)
    {
        $this->maxFailures = $maxFailures;

        return $this;
    }

    /**
     * @return int
     */
    public function getFailures()
    {
        return $this->failures;
    }

    /**
     * @param int $failures
     */
    public function setFailures($failures)
    {
        $this->failures = $failures;

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
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getMaxExceptions()
    {
        return $this->maxExceptions;
    }

    /**
     * @param mixed $maxException
     */
    public function setMaxExceptions($maxExceptions)
    {
        $this->maxExceptions = $maxExceptions;

        return $this;
    }

    /**
     * @return int
     */
    public function getExceptions()
    {
        return $this->exceptions;
    }

    /**
     * @param int $exceptions
     */
    public function setExceptions($exceptions)
    {
        $this->exceptions = $exceptions;

        return $this;
    }
}
