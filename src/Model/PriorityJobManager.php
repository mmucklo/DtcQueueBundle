<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Exception\PriorityException;

abstract class PriorityJobManager extends AbstractJobManager
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

    public function save(Job $job)
    {
        $this->validatePriority($job->getPriority());
        if (!$job->getId()) { // An unsaved job needs it's priority potentially adjusted
            $job->setPriority($this->calculatePriority($job->getPriority()));
        }

        return $this->prioritySave($job);
    }
}
