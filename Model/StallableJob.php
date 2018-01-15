<?php

namespace Dtc\QueueBundle\Model;

abstract class StallableJob extends \Dtc\QueueBundle\Model\RetryableJob
{
    const STATUS_MAX_STALLS = 'max_stalls';
    const STATUS_STALLED = 'stalled';

    protected $maxStalls = 0;
    protected $stalls = 0;

    /**
     * @return int|null
     */
    public function getMaxStalls()
    {
        return $this->maxStalls;
    }

    /**
     * @param int|null $maxStalls
     *
     * @return StallableJob
     */
    public function setMaxStalls($maxStalls)
    {
        $this->maxStalls = $maxStalls;

        return $this;
    }

    /**
     * @return int
     */
    public function getStalls()
    {
        return $this->stalls;
    }

    /**
     * @param int $stalls
     *
     * @return StallableJob
     */
    public function setStalls($stalls)
    {
        $this->stalls = $stalls;

        return $this;
    }
}
