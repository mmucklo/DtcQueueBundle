<?php

namespace Dtc\QueueBundle\Model;

abstract class ArchivableJob extends StallableJob {

    protected $finishedAt;
    protected $elapsed;

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