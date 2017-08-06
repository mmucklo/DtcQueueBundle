<?php

namespace Dtc\QueueBundle\Model;

class Job
{
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';
    const STATUS_NEW = 'new';

    protected $id;
    protected $workerName;
    protected $className;
    protected $method;
    protected $args;
    protected $batch;
    protected $status;
    protected $message;
    protected $priority;
    protected $crcHash;
    protected $locked;
    protected $lockedAt;
    protected $when;
    protected $expire;
    protected $createdAt;
    protected $updatedAt;
    protected $delay;
    protected $startedAt;
    protected $finishedAt;
    protected $maxDuration;
    protected $elapsed;

    /**
     * @var JobManagerInterface
     */
    protected $jobManager;

    /**
     * @return
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return $status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return $locked
     */
    public function getLocked()
    {
        return $this->locked;
    }

    /**
     * @return $lockedAt
     */
    public function getLockedAt()
    {
        return $this->lockedAt;
    }

    /**
     * @return $expire
     */
    public function getExpire()
    {
        return $this->expire;
    }

    /**
     * @param $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @param $locked
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;
    }

    /**
     * @param $lockedAt
     */
    public function setLockedAt($lockedAt)
    {
        $this->lockedAt = $lockedAt;
    }

    /**
     * @param $expire
     */
    public function setExpire($expire)
    {
        $this->expire = $expire;
    }

    /**
     * @return $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return $workerName
     */
    public function getWorkerName()
    {
        return $this->workerName;
    }

    /**
     * @return $className
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @return $method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * @return $batch
     */
    public function getBatch()
    {
        return $this->batch;
    }

    /**
     * @return $priority
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return $crcHash
     */
    public function getCrcHash()
    {
        return $this->crcHash;
    }

    /**
     * @return $when
     */
    public function getWhen()
    {
        return $this->when;
    }

    /**
     * @return $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return $updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @return $jobManager
     */
    public function getJobManager()
    {
        return $this->jobManager;
    }

    /**
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param $workerName
     */
    public function setWorkerName($workerName)
    {
        $this->workerName = $workerName;
    }

    /**
     * @param string $className
     */
    public function setClassName($className)
    {
        $this->className = $className;
    }

    /**
     * @param $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return mixed
     */
    public function getStartedAt()
    {
        return $this->startedAt;
    }

    /**
     * @param mixed $startedAt
     */
    public function setStartedAt(\DateTime $startedAt)
    {
        $this->startedAt = $startedAt;
    }

    /**
     * @return mixed
     */
    public function getFinishedAt()
    {
        return $this->finishedAt;
    }

    /**
     * @param mixed $finishedAt
     */
    public function setFinishedAt($finishedAt)
    {
        $this->finishedAt = $finishedAt;
    }

    /**
     * @param $args
     */
    public function setArgs($args)
    {
        if (!$this->recursiveValidArgs($args)) {
            throw new \Exception('Args must not contain object');
        }

        $this->args = $args;
    }

    protected function recursiveValidArgs($args)
    {
        if (is_array($args)) {
            foreach ($args as $key => $value) {
                if (!$this->recursiveValidArgs($value)) {
                    return false;
                }
            }

            return true;
        } else {
            return !is_object($args);
        }
    }

    /**
     * @param $batch
     */
    public function setBatch($batch)
    {
        $this->batch = $batch;
    }

    /**
     * @param $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

    /**
     * @param $crcHash
     */
    public function setCrcHash($crcHash)
    {
        $this->crcHash = $crcHash;
    }

    /**
     * @param $when
     */
    public function setWhen($when)
    {
        $this->when = $when;
    }

    /**
     * @param $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @param $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param $jobManager
     */
    public function setJobManager($jobManager)
    {
        $this->jobManager = $jobManager;
    }

    protected $worker;

    public function __construct(Worker $worker = null, $batch = false, $priority = 10, \DateTime $when = null)
    {
        $this->worker = $worker;
        if ($worker) {
            $this->jobManager = $worker->getJobManager();
            $this->className = get_class($worker);
            $this->workerName = $worker->getName();
        }

        $this->when = $when;
        $this->batch = $batch ? true : false;
        $this->priority = $priority;
        $this->status = self::STATUS_NEW;
    }

    public function __call($method, $args)
    {
        $this->method = $method;
        $this->setArgs($args);
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        // Make sure the method exists - job should not be created
        if (!is_callable(array($this->worker, $method), true)) {
            throw new \Exception("{$this->className}->{$method}() is not callable");
        }

        $this->jobManager->save($this);

        return $this;
    }

    /**
     * @return $delay
     */
    public function getDelay()
    {
        return $this->delay;
    }

    /**
     * @return $worker
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * @param $delay
     */
    public function setDelay($delay)
    {
        $this->delay = $delay;
    }

    /**
     * @param Worker $worker
     */
    public function setWorker($worker)
    {
        $this->worker = $worker;
    }

    /**
     * @return $elapsed
     */
    public function getElapsed()
    {
        return $this->elapsed;
    }

    /**
     * @param $elapsed
     */
    public function setElapsed($elapsed)
    {
        $this->elapsed = $elapsed;
    }
}
