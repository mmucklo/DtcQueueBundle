<?php

namespace Dtc\QueueBundle\RabbitMQ;

class Job extends \Dtc\QueueBundle\Model\MessageableJobWithId
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
}
