<?php

namespace Dtc\QueueBundle\Model;

class Job
{
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';
    const STATUS_NEW = 'new';
    const STATUS_EXPIRED = 'expired';

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
    protected $whenAt;
    protected $expiresAt;
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
     * @return string|null
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return string The status of the job
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return bool|null
     */
    public function getLocked()
    {
        return $this->locked;
    }

    /**
     * @return \DateTime|null
     */
    public function getLockedAt()
    {
        return $this->lockedAt;
    }

    /**
     * @return \DateTime|null
     */
    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    /**
     * @param string $status The status of the job
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @param bool|null $locked
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;
    }

    /**
     * @param \DateTime|null $lockedAt
     */
    public function setLockedAt($lockedAt)
    {
        $this->lockedAt = $lockedAt;
    }

    /**
     * @param \DateTime $expiresAt
     */
    public function setExpiresAt(\DateTime $expiresAt)
    {
        $this->expiresAt = $expiresAt;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
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
     * @return string
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
     * @return bool
     */
    public function getBatch()
    {
        return $this->batch;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return string
     */
    public function getCrcHash()
    {
        return $this->crcHash;
    }

    /**
     * @return \DateTime|null
     */
    public function getWhenAt()
    {
        return $this->whenAt;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @return JobManagerInterface
     */
    public function getJobManager()
    {
        return $this->jobManager;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param string $workerName
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
     * @param string $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
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
    public function getFinishedAt()
    {
        return $this->finishedAt;
    }

    /**
     * @param \DateTime $finishedAt
     */
    public function setFinishedAt($finishedAt)
    {
        $this->finishedAt = $finishedAt;
    }

    /**
     * @return int|null
     */
    public function getMaxDuration()
    {
        return $this->maxDuration;
    }

    /**
     * @param int|null $maxDuration
     */
    public function setMaxDuration($maxDuration)
    {
        $this->maxDuration = $maxDuration;
    }

    /**
     * @param array $args
     */
    public function setArgs($args)
    {
        if (!$this->validateArgs($args)) {
            throw new \Exception('Args must not contain object');
        }

        $this->args = $args;
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
     * @param bool $batch
     */
    public function setBatch($batch)
    {
        $this->batch = $batch;
    }

    /**
     * @param int $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

    /**
     * @param string $crcHash
     */
    public function setCrcHash($crcHash)
    {
        $this->crcHash = $crcHash;
    }

    /**
     * @param \DateTime $whenAt
     */
    public function setWhenAt(\DateTime $whenAt)
    {
        $this->whenAt = $whenAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt(\DateTime $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt(\DateTime $updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @param JobManagerInterface $jobManager
     */
    public function setJobManager(JobManagerInterface $jobManager)
    {
        $this->jobManager = $jobManager;
    }

    protected $worker;

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

    public function __call($method, $args)
    {
        $this->method = $method;
        $this->setArgs($args);
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();

        // Make sure the method exists - job should not be created
        if (!method_exists($this->worker, $method)) {
            throw new \Exception("{$this->className}->{$method}() does not exist");
        }

        $this->jobManager->save($this);

        return $this;
    }

    /**
     * @return int
     */
    public function getDelay()
    {
        return $this->delay;
    }

    /**
     * @return Worker
     */
    public function getWorker()
    {
        return $this->worker;
    }

    /**
     * @param int $delay Delay in seconds
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
     * @return int
     */
    public function getElapsed()
    {
        return $this->elapsed;
    }

    /**
     * @param int $elapsed
     */
    public function setElapsed($elapsed)
    {
        $this->elapsed = $elapsed;
    }

    /**
     * @return string A json_encoded version of a queueable version of the object
     */
    public function toMessage()
    {
        $arr = array(
            'worker' => $this->getWorkerName(),
            'args' => $this->getArgs(),
            'method' => $this->getMethod(),
            'expiresAt' => ($expiresAt = $this->getExpiresAt()) ? $expiresAt->getTimestamp() : null,
        );

        return json_encode($arr);
    }

    /**
     * @param string $message a json_encoded version of the object
     */
    public function fromMessage($message)
    {
        $arr = json_decode($message, true);
        $this->setWorkerName($arr['worker']);
        $this->setArgs($arr['args']);
        $this->setMethod($arr['method']);
        $expiresAt = $arr['expiresAt'];

        if ($expiresAt) {
            $dateTime = new \DateTime();
            $dateTime->setTimestamp(intval($expiresAt));
            $this->setExpiresAt($dateTime);
        }
    }
}
