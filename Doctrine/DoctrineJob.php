<?php

namespace Dtc\QueueBundle\Doctrine;

use Dtc\QueueBundle\Model\StallableJob;

abstract class DoctrineJob extends StallableJob
{
    protected $locked;
    protected $lockedAt;

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
     * @param bool|null $locked
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;

        return $this;
    }

    /**
     * @param \DateTime|null $lockedAt
     */
    public function setLockedAt($lockedAt)
    {
        $this->lockedAt = $lockedAt;

        return $this;
    }
}
