<?php

namespace Dtc\QueueBundle\RabbitMQ;

use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\RetryableJob;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Manager\PriorityJobManager;
use Dtc\QueueBundle\Exception\ArgumentsNotSetException;
use Dtc\QueueBundle\Exception\ClassNotSubclassException;
use Dtc\QueueBundle\Exception\PriorityException;
use Dtc\QueueBundle\Exception\UnsupportedException;
use Dtc\QueueBundle\Manager\RunManager;
use Dtc\QueueBundle\Manager\JobTimingManager;
use Dtc\QueueBundle\Util\Util;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;

class JobManager extends PriorityJobManager
{
    /** @var AMQPChannel */
    protected $channel;

    /** @var AbstractConnection */
    protected $connection;
    protected $queueArgs;
    protected $exchangeArgs;

    protected $channelSetup = false;

    protected $hostname;
    protected $pid;

    public function __construct(RunManager $runManager, JobTimingManager $jobTimingManager, $jobClass)
    {
        $this->hostname = gethostname() ?: '';
        $this->pid = getmypid();
        parent::__construct($runManager, $jobTimingManager, $jobClass);
    }

    /**
     * @param string $exchange
     * @param string $type
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $autoDelete
     */
    public function setExchangeArgs($exchange, $type, $passive, $durable, $autoDelete)
    {
        $this->exchangeArgs = [$exchange, $type, $passive, $durable, $autoDelete];
    }

    /**
     * @param string $queue
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $exclusive
     * @param bool   $autoDelete
     *
     * @throws PriorityException
     */
    public function setQueueArgs($queue, $passive, $durable, $exclusive, $autoDelete)
    {
        $arguments = [$queue, $passive, $durable, $exclusive, $autoDelete];

        $this->queueArgs = $arguments;
        if (!ctype_digit(strval($this->maxPriority))) {
            throw new PriorityException('Max Priority ('.$this->maxPriority.') needs to be a non-negative integer');
        }
        if (strval(intval($this->maxPriority)) !== strval($this->maxPriority)) {
            throw new PriorityException('Priority is higher than '.PHP_INT_MAX);
        }
    }

    public function setAMQPConnection(AbstractConnection $connection)
    {
        $this->connection = $connection;
        $this->channel = $connection->channel();
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @throws ArgumentsNotSetException
     */
    protected function checkChannelArgs()
    {
        if (empty($this->queueArgs)) {
            throw new ArgumentsNotSetException(__METHOD__.': queue args need to be set via setQueueArgs(...)');
        }
        if (empty($this->exchangeArgs)) {
            throw new ArgumentsNotSetException(__METHOD__.': exchange args need to be set via setExchangeArgs(...)');
        }
    }

    protected function performChannelSetup()
    {
        call_user_func_array([$this->channel, 'exchange_declare'], $this->exchangeArgs);
        if ($this->maxPriority) {
            array_push($this->queueArgs, false);
            array_push($this->queueArgs, ['x-max-priority' => ['I', intval($this->maxPriority)]]);
        }
        call_user_func_array([$this->channel, 'queue_declare'], $this->queueArgs);
        $this->channel->queue_bind($this->queueArgs[0], $this->exchangeArgs[0]);
    }

    /**
     * @throws ArgumentsNotSetException
     */
    public function setupChannel()
    {
        $this->checkChannelArgs();

        if (!$this->channelSetup) {
            $this->performChannelSetup();
            $this->channelSetup = true;
        }
    }

    /**
     * @param \Dtc\QueueBundle\Model\Job $job
     *
     * @return \Dtc\QueueBundle\Model\Job
     *
     * @throws ClassNotSubclassException
     * @throws PriorityException
     * @throws ArgumentsNotSetException
     */
    public function prioritySave(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job instanceof Job) {
            throw new ClassNotSubclassException('Must be derived from '.Job::class);
        }

        $this->setupChannel();

        $this->validateSaveable($job);
        $this->setJobId($job);

        $this->publishJob($job);

        return $job;
    }

    protected function publishJob(Job $job)
    {
        $msg = new AMQPMessage($job->toMessage());
        $this->setMsgPriority($msg, $job);

        $this->channel->basic_publish($msg, $this->exchangeArgs[0]);
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
     * Sets the priority of the AMQPMessage.
     *
     * @param AMQPMessage                $msg
     * @param \Dtc\QueueBundle\Model\Job $job
     */
    protected function setMsgPriority(AMQPMessage $msg, \Dtc\QueueBundle\Model\Job $job)
    {
        if (null !== $this->maxPriority) {
            $priority = $job->getPriority();
            $msg->set('priority', $priority);
        }
    }

    protected function calculatePriority($priority)
    {
        $priority = parent::calculatePriority($priority);
        if (null === $priority) {
            return 0;
        }

        return $priority;
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

    /**
     * @param string|null $workerName
     * @param string|null $methodName
     * @param bool        $prioritize
     *
     * @throws UnsupportedException
     */
    protected function verifyGetJobArgs($workerName = null, $methodName = null, $prioritize = true)
    {
        if (null !== $workerName || null !== $methodName || (null !== $this->maxPriority && true !== $prioritize)) {
            throw new UnsupportedException('Unsupported');
        }
    }

    /**
     * @param string|null $workerName
     * @param string|null $methodName
     *
     * @throws UnsupportedException
     * @throws ArgumentsNotSetException
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        $this->verifyGetJobArgs($workerName, $methodName, $prioritize);
        $this->setupChannel();

        do {
            $expiredJob = false;
            $job = $this->findJob($expiredJob, $runId);
        } while ($expiredJob);

        return $job;
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

    protected function resetJob(RetryableJob $job)
    {
        if (!$job instanceof Job) {
            throw new \InvalidArgumentException('$job must be instance of '.Job::class);
        }
        $job->setStatus(BaseJob::STATUS_NEW);
        $job->setMessage(null);
        $job->setStartedAt(null);
        $job->setRetries($job->getRetries() + 1);
        $job->setUpdatedAt(Util::getMicrotimeDateTime());
        $this->publishJob($job);

        return true;
    }

    // Save History get called upon completion of the job
    protected function retryableSaveHistory(RetryableJob $job, $retry)
    {
        if (!$job instanceof Job) {
            throw new ClassNotSubclassException("Expected \Dtc\QueueBundle\RabbitMQ\Job, got ".get_class($job));
        }
        $deliveryTag = $job->getDeliveryTag();
        $this->channel->basic_ack($deliveryTag);

        return;
    }

    public function __destruct()
    {
        // There's some kind of problem trying to close the channel, otherwise we'd call $this->channel->close() at this point.
    }
}
