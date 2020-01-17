<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\ClassNotSubclassException;
use Dtc\QueueBundle\Exception\PriorityException;
use Dtc\QueueBundle\Model\RetryableJob;

trait SaveableTrait
{
    /**
     * @throws PriorityException
     * @throws ClassNotSubclassException
     */
    protected function validateSaveable(\Dtc\QueueBundle\Model\Job $job)
    {
        if (null !== $job->getPriority() && !isset($this->maxPriority)) {
            throw new PriorityException('This queue does not support priorities');
        }

        if (!$job instanceof RetryableJob) {
            throw new ClassNotSubclassException('Job needs to be instance of '.RetryableJob::class);
        }
    }
}
