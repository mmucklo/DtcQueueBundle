<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\PriorityException;
use Dtc\QueueBundle\Model\BaseRetryableJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;

abstract class PriorityJobManager extends BaseRetryableJobManager
{
    const PRIORITY_ASC = 'asc';
    const PRIORITY_DESC = 'desc';

    protected $maxPriority;
    protected $priorityDirection = self::PRIORITY_DESC;

    /**
     * @return mixed
     */
    public function getMaxPriority()
    {
        return $this->maxPriority;
    }

    /**
     * @param mixed $maxPriority
     */
    public function setMaxPriority($maxPriority)
    {
        $this->maxPriority = $maxPriority;
    }

    /**
     * @return mixed
     */
    public function getPriorityDirection()
    {
        return $this->priorityDirection;
    }

    /**
     * @param mixed $priorityDirection
     */
    public function setPriorityDirection($priorityDirection)
    {
        $this->priorityDirection = $priorityDirection;
    }

    protected function validatePriority($priority)
    {
        if (null === $priority) {
            return;
        }

        if (!ctype_digit(strval($priority))) {
            throw new PriorityException("Priority ($priority) needs to be a positive integer");
        }
        if (strval(intval($priority)) !== strval($priority)) {
            throw new PriorityException("Priority ($priority) needs to be less than ".PHP_INT_MAX);
        }
        $maxPriority = $this->getMaxPriority();
        if (intval($priority) > $maxPriority) {
            throw new PriorityException("Priority ($priority) must be less than ".$maxPriority);
        }
    }

    /**
     * Returns the prioirty in ASCENDING order regardless of the User's choice of direction
     *   (for storing RabbitMQ, Mysql, others)
     * @param $priority
     * @return mixed
     */
    protected function calculatePriority($priority)
    {
        if (null === $priority) {
            return $priority;
        }
        if (self::PRIORITY_DESC === $this->priorityDirection) {
            $priority = $this->maxPriority - $priority;
        }

        return $priority;
    }

    protected function findHigherPriority($priority1, $priority2)
    {
        if (null === $priority1) {
            return $priority2;
        }
        if (null === $priority2) {
            return $priority1;
        }

        if (self::PRIORITY_DESC === $this->priorityDirection) {
            return min($priority1, $priority2);
        } else {
            return max($priority1, $priority2);
        }
    }

    abstract protected function prioritySave(Job $job);

    /**
     * @param Job $job
     *
     * @throws PriorityException
     */
    protected function retryableSave(BaseRetryableJob $job)
    {
        $this->validatePriority($job->getPriority());
        if (!$job->getId()) { // An unsaved job needs it's priority potentially adjusted
            $job->setPriority($this->calculatePriority($job->getPriority()));
        }

        $result = $this->prioritySave($job);

        return $result;
    }
}
