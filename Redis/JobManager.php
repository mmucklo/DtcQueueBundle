<?php

namespace Dtc\QueueBundle\Redis;

use Dtc\QueueBundle\Model\PriorityJobManager;

/**
 * For future implementation.
 */
class JobManager extends PriorityJobManager
{
    /** @var RedisInterface */
    protected $redis;

    /** @var string */
    protected $hashKeyPrefix;

    public function __construct(RunManager $runManager, JobTimingManager $jobTimingManager, $jobClass, $hashKeyPrefix)
    {
        $this->hashKeyPrefix = $hashKeyPrefix;
        parent::__construct($runManager, $jobTimingManager, $jobClass);
    }

    public function setRedis(RedisInterface $redis)
    {
        $this->redis = $redis;
    }

    protected function getPriorityQueueHashKey()
    {
        return $this->hashKeyPrefix.'_priority';
    }

    protected function getWhenAtQueueHashKey()
    {
        return $this->hashKeyPrefix.'_when_at';
    }

    /**
     * @param \Dtc\QueueBundle\Model\Job $job
     *
     * @return \Dtc\QueueBundle\Model\Job
     *
     * @throws ClassNotSubclassException
     */
    public function prioritySave(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job instanceof Job) {
            throw new ClassNotSubclassException('Must be derived from '.Job::class);
        }

        $this->setupChannel();

        $this->validateSaveable($job);
        $this->setJobId($job);

        $msg = new AMQPMessage($job->toMessage());
        $this->setMsgPriority($msg, $job);

        $this->channel->basic_publish($msg, $this->exchangeArgs[0]);

        return $job;
    }

    /**
     * Attach a unique id to a job since RabbitMQ will not.
     *
     * @param \Dtc\QueueBundle\Model\Job $job
     */
    protected function setJobId(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job->getId()) {
            $job->setId(uniqid($this->hostname.'-'.$this->pid, true));
        }
    }

    /**
     * Returns the prioirty in descending order, except if maxPrioirty is null, then prioirty is 0.
     */
    protected function calculatePriority($priority)
    {
        $priority = parent::calculatePriority($priority);
        if (null === $priority) {
            return 0;
        }

        if (null === $this->maxPriority) {
            return 0;
        }

        // Redis priority should be in DESC order
        return $this->maxPriority - $priority;
    }

    /**
     * @param \Dtc\QueueBundle\Model\Job $job
     *
     * @throws PriorityException
     * @throws ClassNotSubclassException
     */
    protected function validateSaveable(\Dtc\QueueBundle\Model\Job $job)
    {
        if (null !== $job->getPriority() && null === $this->maxPriority) {
            throw new PriorityException('This queue does not support priorities');
        }

        if (!$job instanceof Job) {
            throw new ClassNotSubclassException('Job needs to be instance of '.Job::class);
        }
    }

    protected function verifyGetJobArgs($workerName = null, $methodName = null, $prioritize = true)
    {
        if (null !== $workerName || null !== $methodName || (null !== $this->maxPriority && true !== $prioritize)) {
            throw new UnsupportedException('Unsupported');
        }
    }

    /**
     * @param string $workerName
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        // First thing migrate any jobs from When queue to Prioirty queue

        $this->verifyGetJobArgs($workerName, $methodName, $prioritize);
        $this->setupChannel();

        do {
            $expiredJob = false;
            $job = $this->findJob($expiredJob, $runId);
        } while ($expiredJob);

        return $job;
    }

    protected function getCurTime()
    {
        $time = intval(microtime(true) * 1000000);

        return $time;
    }

    protected function prioritizeReadyJobs()
    {
        // Get all ready jobs
        $maxTime = $this->getCurTime();
        $hashKey = $this->getWhenAtQueueHashKey();
        while ($jobDef = $this->redis->zPopByMaxScore($hashKey, $maxTime)) {
        }
    }

    /**
     * @param bool $expiredJob
     * @param $runId
     *
     * @return Job|null
     */
    protected function findJob(&$expiredJob, $runId)
    {
        $message = $this->channel->basic_get($this->queueArgs[0]);
        if ($message) {
            $job = new Job();
            $job->fromMessage($message->body);
            $job->setRunId($runId);

            if (($expiresAt = $job->getExpiresAt()) && $expiresAt->getTimestamp() < time()) {
                $expiredJob = true;
                $this->channel->basic_nack($message->delivery_info['delivery_tag']);
                $this->jobTiminigManager->recordTiming(JobTiming::STATUS_FINISHED_EXPIRED);

                return null;
            }
            $job->setDeliveryTag($message->delivery_info['delivery_tag']);

            return $job;
        }

        return null;
    }

    // Save History get called upon completion of the job
    public function saveHistory(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job instanceof Job) {
            throw new ClassNotSubclassException("Expected \Dtc\QueueBundle\RabbitMQ\Job, got ".get_class($job));
        }
        $deliveryTag = $job->getDeliveryTag();
        $this->channel->basic_ack($deliveryTag);

        return;
    }
}
