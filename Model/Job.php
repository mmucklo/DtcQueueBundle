<?php
namespace Dtc\QueueBundle\Model;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(db="queue", collection="job")
 * @ODM\Index(keys={"className"="asc"})
 */
abstract class Job
{
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';
    const STATUS_NEW = 'new';

    /**
     * @ODM\Id
     */
    protected $id;

    /**
     * @ODM\Field(type="string", name="worker_name")
     */
    protected $workerName;

    /**
     * @ODM\Field(type="string", name="class_name")
     */
    protected $className;

    /**
     * @ODM\Field(type="string")
     */
    protected $method;

    /**
     * @ODM\Field(type="string")
     */
    protected $args;

    /**
     * @ODM\Field(type="string")
     */
    protected $batch;

    /**
     * @ODM\Field(type="string")
     */
    protected $status;

    /**
     * @ODM\Field(type="string")
     */
    protected $message;

    /**
     * @ODM\Field(type="string")
     */
    protected $priority;

    /**
     * @ODM\Field(type="string")
     */
    protected $crcHash;

    /**
     * If job is locked (checked out by a worker)
     *
     * @ODM\Field(type="boolean")
     */
    protected $locked;

    /**
     * When the job get locked
     *
     * @ODM\Field(type="date", name="locked_at")
     */
    protected $lockedAt;

    /**
     * When the job should start
     *
     * @ODM\Field(type="date")
     */
    protected $when;

    /**
     * When the job is estimated to be finished by
     *     If the job does not finish by expire time, a differnt worker
     *     will pick up the job and attempt to finish it
     *
     * @ODM\Field(type="date")
     */
    protected $expire;

    /**
     * @ODM\Field(type="date")
     */
    protected $createdAt;

    /**
     * @ODM\Field(type="date")
     */
    protected $updatedAt;

    protected $jobManager;

    /**
     * @return the $message
     */
    public function getMessage()
    {
        return $this->message;
    }

	/**
     * @param field_type $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

	/**
     * @return the $status
     */
    public function getStatus()
    {
        return $this->status;
    }

	/**
     * @return the $locked
     */
    public function getLocked()
    {
        return $this->locked;
    }

	/**
     * @return the $lockedAt
     */
    public function getLockedAt()
    {
        return $this->lockedAt;
    }

	/**
     * @return the $expire
     */
    public function getExpire()
    {
        return $this->expire;
    }

	/**
     * @param field_type $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

	/**
     * @param field_type $locked
     */
    public function setLocked($locked)
    {
        $this->locked = $locked;
    }

	/**
     * @param field_type $lockedAt
     */
    public function setLockedAt($lockedAt)
    {
        $this->lockedAt = $lockedAt;
    }

	/**
     * @param field_type $expire
     */
    public function setExpire($expire)
    {
        $this->expire = $expire;
    }

	/**
     * @return the $id
     */
    public function getId()
    {
        return $this->id;
    }

	/**
     * @return the $workerName
     */
    public function getWorkerName()
    {
        return $this->workerName;
    }

	/**
     * @return the $className
     */
    public function getClassName()
    {
        return $this->className;
    }

	/**
     * @return the $method
     */
    public function getMethod()
    {
        return $this->method;
    }

	/**
     * @return the $args
     */
    public function getArgs()
    {
        return $this->args;
    }

	/**
     * @return the $batch
     */
    public function getBatch()
    {
        return $this->batch;
    }

	/**
     * @return the $priority
     */
    public function getPriority()
    {
        return $this->priority;
    }

	/**
     * @return the $crcHash
     */
    public function getCrcHash()
    {
        return $this->crcHash;
    }

	/**
     * @return the $when
     */
    public function getWhen()
    {
        return $this->when;
    }

	/**
     * @return the $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

	/**
     * @return the $updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

	/**
     * @return the $jobManager
     */
    public function getJobManager()
    {
        return $this->jobManager;
    }

	/**
     * @param field_type $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

	/**
     * @param field_type $workerName
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
     * @param field_type $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

	/**
     * @param field_type $args
     */
    public function setArgs($args)
    {
        $this->args = $args;
    }

	/**
     * @param field_type $batch
     */
    public function setBatch($batch)
    {
        $this->batch = $batch;
    }

	/**
     * @param field_type $priority
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
    }

	/**
     * @param field_type $crcHash
     */
    public function setCrcHash($crcHash)
    {
        $this->crcHash = $crcHash;
    }

	/**
     * @param field_type $when
     */
    public function setWhen($when)
    {
        $this->when = $when;
    }

	/**
     * @param field_type $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

	/**
     * @param field_type $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

	/**
     * @param field_type $jobManager
     */
    public function setJobManager($jobManager)
    {
        $this->jobManager = $jobManager;
    }

	public function __construct(Worker $worker, \DateTime $when, $batch, $priority)
    {
        $this->jobManager = $worker->getJobManager();
        $this->className = get_class($worker);
        $this->workerName = $worker->getName();

        $this->when = $when;
        $this->batch = $batch;
        $this->priority = $priority;
        $this->status = self::STATUS_NEW;
    }

    public function __call($method, $args)
    {
        $job = clone ($this);
        $job->method = $method;
        $job->args = $args;
        $job->jobManager = null;
        $job->createdAt = new \DateTime();
        $job->updatedAt = new \DateTime();

        $this->jobManager->save($job);
        return $job;
    }
}
