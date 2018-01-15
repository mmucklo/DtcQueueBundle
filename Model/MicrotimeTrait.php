<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Util\Util;

trait MicrotimeTrait
{
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
        $whenUs = isset($this->whenUs) ? $this->whenUs : null;
        if ($whenUs) {
            return Util::getDateTimeFromDecimalFormat($whenUs);
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
        return isset($this->whenUs) ? $this->whenUs : null;
    }
}
