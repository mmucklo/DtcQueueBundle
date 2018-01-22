<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\UnsupportedException;

trait VerifyTrait
{
    /**
     * @param string|null $workerName
     * @param string|null $methodName
     * @param bool        $prioritize
     *
     * @throws UnsupportedException
     */
    protected function verifyGetJobArgs($workerName = null, $methodName = null, $prioritize = true)
    {
        if (null !== $workerName || null !== $methodName || (isset($this->maxPriority) && true !== $prioritize)) {
            throw new UnsupportedException('Unsupported');
        }
    }
}
