<?php

namespace Dtc\QueueBundle\Model;

abstract class BaseJob
{
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';
    const STATUS_NEW = 'new';
    const STATUS_RUNNING = 'running';

    /**
     * @var JobManagerInterface
     */
    protected $jobManager;
    protected $worker;
    protected $workerName;
    protected $className;
    protected $args;
    protected $batch;
    protected $method;
    protected $priority;
    protected $whenAt;
    protected $status;

    public function __construct(Worker $worker = null, $batch = false, $priority = 10, \DateTime $whenAt = null)
    {
        $this->worker = $worker;
        if ($worker) {
            $this->jobManager = $worker->getJobManager();
            $this->className = get_class($worker);
            $this->workerName = $worker->getName();
        }

        $this->whenAt = $whenAt;
        $this->batch = $batch ? true : false;
        $this->priority = $priority;
        $this->status = self::STATUS_NEW;
    }

    /**
     * @param string $status The status of the job
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return string The status of the job
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return \DateTime|null
     */
    public function getWhenAt()
    {
        return $this->whenAt;
    }

    /**
     * @param \DateTime $whenAt
     */
    public function setWhenAt(\DateTime $whenAt)
    {
        $this->whenAt = $whenAt;

        return $this;
    }

    /**
     * @return Worker
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * @param Worker $worker
     */
    public function setWorker($worker)
    {
        $this->worker = $worker;

        return $this;
    }

    /**
     * @return bool
     */
    public function getBatch()
    {
        return $this->batch;
    }

    /**
     * @param bool $batch
     */
    public function setBatch($batch)
    {
        $this->batch = $batch;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @param $args
     */
    public function setArgs($args)
    {
        if (!$this->validateArgs($args)) {
            throw new \InvalidArgumentException('Args must not contain object');
        }

        $this->args = $args;

        return $this;
    }

    protected function validateArgs($args)
    {
        if (is_array($args)) {
            foreach ($args as $key => $value) {
                if (!$this->validateArgs($value)) {
                    return false;
                }
            }

            return true;
        } else {
            return !is_object($args);
        }
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @param string $workerName
     */
    public function setWorkerName($workerName)
    {
        $this->workerName = $workerName;

        return $this;
    }

    /**
     * @param string $className
     */
    public function setClassName($className)
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @return string
     */
    public function getWorkerName()
    {
        return $this->workerName;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @return JobManagerInterface
     */
    public function getJobManager()
    {
        return $this->jobManager;
    }
}
