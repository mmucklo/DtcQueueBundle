<?php

namespace Dtc\QueueBundle\Model;

abstract class RetryableJob extends \Dtc\QueueBundle\Model\BaseRetryableJob
{
    const STATUS_MAX_EXCEPTION = 'max_exception';
    const STATUS_MAX_STALLED = 'max_stallled';

    protected $maxStalled = 0;
    protected $stalledCount = 0;
    protected $maxException = 0;
    protected $exceptionCount = 0;

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
     * @return mixed
     */
    public function getMaxException()
    {
        return $this->maxException;
    }

    /**
     * @param mixed $maxException
     */
    public function setMaxException($maxException)
    {
        $this->maxException = $maxException;
        return $this;
    }

    /**
     * @return int
     */
    public function getExceptionCount(): int
    {
        return $this->exceptionCount;
    }

    /**
     * @param int $exceptionCount
     */
    public function setExceptionCount(int $exceptionCount)
    {
        $this->exceptionCount = $exceptionCount;
        return $this;
    }
}
