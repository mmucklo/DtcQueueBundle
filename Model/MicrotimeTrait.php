<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Util\Util;

trait MicrotimeTrait
{
    protected $whenUs;

    public function setWhenAt(\DateTime $whenAt)
    {
        parent::setWhenAt($whenAt);

        return $this->setWhenUs(Util::getMicrotimeDecimalFormat($whenAt));
    }

    /**
     * @return \DateTime|null
     */
    public function getWhenAt()
    {
        if ($this->whenUs) {
            return Util::getDateTimeFromDecimalFormat($this->whenUs);
        }

        return null;
    }

    /**
     * @param string
     */
    public function setWhenUs($whenUs)
    {
        $this->whenUs = $whenUs;

        return $this;
    }

    /**
     * @param string
     */
    public function getWhenUs()
    {
        return $this->whenUs;
    }
}
