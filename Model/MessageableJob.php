<?php

namespace Dtc\QueueBundle\Model;

abstract class MessageableJob extends BaseRetryableJob
{
    protected function toMessageArray()
    {
        return array(
            'worker' => $this->getWorkerName(),
            'args' => $this->getArgs(),
            'method' => $this->getMethod(),
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
        if (isset($arr['worker'])) {
            $this->setWorkerName($arr['worker']);
        }
        if (isset($arr['args'])) {
            $this->setArgs($arr['args']);
        }
        if (isset($arr['method'])) {
            $this->setMethod($arr['method']);
        }
        if (isset($arr['retries'])) {
            $this->setRetries($arr['retries']);
        }
        if (isset($arr['maxRetries'])) {
            $this->setMaxRetries($arr['maxRetries']);
        }
        if (isset($arr['failures'])) {
            $this->setFailures($arr['failures']);
        }
        if (isset($arr['maxFailures'])) {
            $this->setMaxFailures($arr['maxFailures']);
        }
        if (isset($arr['exceptions'])) {
            $this->setExceptions($arr['exceptions']);
        }
        if (isset($arr['maxExceptions'])) {
            $this->setMaxExceptions($arr['maxExceptions']);
        }

        if (isset($arr['expiresAt'])) {
            $expiresAt = $arr['expiresAt'];
            if ($expiresAt) {
                $dateTime = \DateTime::createFromFormat('U.u', $expiresAt);
                if ($dateTime) {
                    $this->setExpiresAt($dateTime);
                }
            }
        }
    }
}
