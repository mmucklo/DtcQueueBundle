<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\Exception\PriorityException;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\Job;

abstract class PriorityJobManager extends RetryableJobManager
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
        if (null !== $maxPriority && intval($priority) > $maxPriority) {
            throw new PriorityException("Priority ($priority) must be less than ".$maxPriority);
        }
    }

    /**
     * Returns the prioirty in ASCENDING order regardless of the User's choice of direction
     *   (for storing RabbitMQ, Mysql, others).
     *
     * @param $priority
     *
     * @return mixed
     */
    protected function calculatePriority($priority)
    {
        if (null === $priority) {
            return $priority;
        }

        if (null === $this->maxPriority) {
            return null;
        }

        if (self::PRIORITY_DESC === $this->priorityDirection) {
            $priority = $this->maxPriority - $priority;
        }

        return $priority;
    }

    abstract protected function prioritySave(Job $job);

    /**
     * @param Job $job
     *
     * @throws PriorityException
     */
    protected function retryableSave(RetryableJob $job)
    {
        $this->validatePriority($job->getPriority());
        if (!$job->getId()) { // An unsaved job needs it's priority potentially adjusted
            $job->setPriority($this->calculatePriority($job->getPriority()));
        }

        $result = $this->prioritySave($job);

        return $result;
    }
}
