<?php

namespace Dtc\QueueBundle\Model;

abstract class MessageableJob extends Job
{
    protected function toMessageArray()
    {
        return array(
            'worker' => $this->getWorkerName(),
            'args' => $this->getArgs(),
            'method' => $this->getMethod(),
            'expiresAt' => ($expiresAt = $this->getExpiresAt()) ? $expiresAt->format('U.u') : null,
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
