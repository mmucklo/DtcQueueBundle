<?php

namespace Dtc\QueueBundle\RabbitMQ;

class Job extends \Dtc\QueueBundle\Model\Job
{
    protected $deliveryTag;

    /**
     * @return string
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
        $arr = $this->toMessageArray();
        $arr['id'] = $this->getId();

        return json_encode($arr);
    }

    /**
     * @param string $message a json_encoded version of the object
     */
    public function fromMessage($message)
    {
        $arr = json_decode($message, true);
        if (is_array($arr)) {
            $this->fromMessageArray($arr);
            if (isset($arr['id'])) {
                $this->setId($arr['id']);
            }
        }
    }
}
