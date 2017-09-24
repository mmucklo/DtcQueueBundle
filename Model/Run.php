<?php

namespace Dtc\QueueBundle\Model;

/**
 * Class QueueWorker.
 *
 * Placeholder for future Queue Worker that sits and drains the queue
 */
class Run
{
    protected $id;
    protected $startedAt;
    protected $endedAt;
    protected $duration; // How long to run for in seconds
    protected $lastHeartbeatAt;
    protected $maxCount;
    protected $processed; // Number of jobs processed
    protected $hostname;
    protected $pid;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return \DateTime|null
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @param \DateTime $startedAt
     */
    public function setStartedAt(\DateTime $startedAt)
    {
        $this->startedAt = $startedAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getEndedAt()
    {
        return $this->endedAt;
    }

    /**
     * @param \DateTime $endedAt
     */
    public function setEndedAt(\DateTime $endedAt)
    {
        $this->endedAt = $endedAt;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    /**
     * @return \DateTime|null
     */
    public function getLastHeartbeatAt()
    {
        return $this->lastHeartbeatAt;
    }

    /**
     * @param \DateTime $lastHeartbeatAt
     */
    public function setLastHeartbeatAt(\DateTime $lastHeartbeatAt)
    {
        $this->lastHeartbeatAt = $lastHeartbeatAt;
    }

    /**
     * @return int
     */
    public function getMaxCount()
    {
        return $this->maxCount;
    }

    /**
     * @param int $maxCount
     */
    public function setMaxCount($maxCount)
    {
        $this->maxCount = $maxCount;
    }

    /**
     * @return int
     */
    public function getProcessed()
    {
        return $this->processed;
    }

    /**
     * @param int $processed
     */
    public function setProcessed($processed)
    {
        $this->processed = $processed;
    }

    /**
     * @return string|null
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * @param string $hostname
     */
    public function setHostname($hostname)
    {
        $this->hostname = $hostname;
    }

    /**
     * @return int|null
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @param int $pid
     */
    public function setPid($pid)
    {
        $this->pid = $pid;
    }
}
