<?php

namespace Dtc\QueueBundle\RabbitMQ;

class Job extends \Dtc\QueueBundle\Model\Job
{
    protected $deliveryTag;

    /**
     * @return mixed
     */
    public function getDeliveryTag()
    {
        return $this->deliveryTag;
    }

    /**
     * @param mixed $deliveryTag
     */
    public function setDeliveryTag($deliveryTag)
    {
        $this->deliveryTag = $deliveryTag;
    }

    /**
     * @return string A json_encoded version of a queueable version of the object
     */
    public function toMessage()
    {
        $arr = array(
            'args' => $this->getArgs(),
            'id' => $this->getId(),
            'expiresAt' => ($expiresAt = $this->getExpiresAt()) ? $expiresAt->getTimestamp() : null,
            'method' => $this->getMethod(),
            'worker' => $this->getWorkerName(),
        );

        return json_encode($arr);
    }

    /**
     * @param string $message a json_encoded version of the object
     */
    public function fromMessage($message)
    {
        $arr = json_decode($message, true);
        $this->setArgs($arr['args']);
        $this->setId($arr['id']);
        $this->setMethod($arr['method']);
        $this->setWorkerName($arr['worker']);
        $expiresAt = $arr['expiresAt'];
        if ($expiresAt) {
            $dateTime = new \DateTime();
            $dateTime->setTimestamp(intval($expiresAt));
            $this->setExpiresAt($dateTime);
        }
    }
}
