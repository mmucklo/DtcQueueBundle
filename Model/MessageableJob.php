<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\Util\Util;

abstract class MessageableJob extends RetryableJob
{
    public function __construct(Worker $worker = null, $batch = false, $priority = null, \DateTime $whenAt = null)
    {
        parent::__construct($worker, $batch, $priority, $whenAt);
        if (!$this->getWhenAt()) {
            $this->setWhenAt(Util::getMicrotimeDateTime());
        }
    }

    protected function toMessageArray()
    {
        return array(
            'worker' => $this->getWorkerName(),
            'args' => $this->getArgs(),
            'method' => $this->getMethod(),
            'priority' => $this->getPriority(),
            'whenAt' => $this->getWhenAt()->format('U.u'),
            'createdAt' => $this->getCreatedAt()->format('U.u'),
            'updatedAt' => $this->getUpdatedAt()->format('U.u'),
            'expiresAt' => ($expiresAt = $this->getExpiresAt()) ? $expiresAt->format('U.u') : null,
            'retries' => $this->getRetries(),
            'maxRetries' => $this->getMaxRetries(),
            'failures' => $this->getFailures(),
            'maxFailures' => $this->getMaxFailures(),
            'exceptions' => $this->getExceptions(),
            'maxExceptions' => $this->getMaxExceptions(),
        );
    }

    /**
     * @return string A json_encoded version of a queueable version of the object
     */
    public function toMessage()
    {
        return json_encode($this->toMessageArray());
    }

    /**
     * @param string $message a json_encoded version of the object
     */
    public function fromMessage($message)
    {
        $arr = json_decode($message, true);
        if (is_array($arr)) {
            $this->fromMessageArray($arr);
        }
    }

    protected function fromMessageArray(array $arr)
    {
        $this->setByList(['args', 'priority', 'method'], $arr);
        if (isset($arr['worker'])) {
            $this->setWorkerName($arr['worker']);
        }

        $this->fromMessageArrayRetries($arr);
        foreach (['expiresAt', 'createdAt', 'updatedAt', 'whenAt'] as $dateField) {
            if (isset($arr[$dateField])) {
                $this->setDateTimeField($dateField, $arr[$dateField]);
            }
        }
    }

    /**
     * @param $dateField string
     * @param $timeStr string|null
     */
    private function setDateTimeField($dateField, $timeStr)
    {
        if ($timeStr) {
            $dateTime = \DateTime::createFromFormat('U.u', $timeStr, new \DateTimeZone(date_default_timezone_get()));
            if (false !== $dateTime) {
                $method = 'set'.ucfirst($dateField);
                $this->$method($dateTime);
            }
        }
    }

    private function setByList(array $list, array $arr)
    {
        foreach ($list as $key) {
            if (isset($arr[$key])) {
                $method = 'set'.ucfirst($key);
                $this->$method($arr[$key]);
            }
        }
    }

    protected function fromMessagearrayRetries(array $arr)
    {
        $this->setByList(['retries', 'maxRetries', 'failures', 'maxFailures', 'exceptions', 'maxExceptions'], $arr);
    }
}
