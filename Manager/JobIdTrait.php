<?php

namespace Dtc\QueueBundle\Manager;

trait JobIdTrait
{
    /**
     * Attach a unique id to a job since RabbitMQ will not.
     *
     * @param \Dtc\QueueBundle\Model\Job $job
     */
    protected function setJobId(\Dtc\QueueBundle\Model\Job $job)
    {
        $pid = isset($this->pid) ? $this->pid : null;
        $hostname = isset($this->hostname) ? $this->hostname : null;
        if (!$job->getId()) {
            $job->setId(uniqid($hostname.'-'.$pid, true));
        }
    }
}
