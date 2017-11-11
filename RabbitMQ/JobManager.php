<?php

namespace Dtc\QueueBundle\RabbitMQ;

use Dtc\QueueBundle\Model\PriorityJobManager;
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

    public function __construct()
    {
        $this->hostname = gethostname() ?: '';
        $this->pid = getmypid();
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
     */
    public function setQueueArgs($queue, $passive, $durable, $exclusive, $autoDelete)
    {
        $arguments = [$queue, $passive, $durable, $exclusive, $autoDelete];

        $this->queueArgs = $arguments;
        if (!ctype_digit(strval($this->maxPriority))) {
            throw new \Exception('Max Priority ('.$this->maxPriority.') needs to be a non-negative integer');
        }
        if (strval(intval($this->maxPriority)) !== strval($this->maxPriority)) {
            throw new \Exception('Priority is higher than '.PHP_INT_MAX);
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

    protected function checkChannelArgs()
    {
        if (empty($this->queueArgs)) {
            throw new \Exception(__METHOD__.': queue args need to be set via setQueueArgs(...)');
        }
        if (empty($this->exchangeArgs)) {
            throw new \Exception(__METHOD__.': exchange args need to be set via setExchangeArgs(...)');
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
     * @throws \Exception
     */
    public function prioritySave(\Dtc\QueueBundle\Model\Job $job)
    {
        if (!$job instanceof Job) {
            throw new \Exception('Must be derived from '.Job::class);
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
     * @throws \Exception
     */
    protected function validateSaveable(\Dtc\QueueBundle\Model\Job $job)
    {
        if (null !== $job->getPriority() && null === $this->maxPriority) {
            throw new \Exception('This queue does not support priorities');
        }

        if (!$job instanceof Job) {
            throw new \Exception('Job needs to be instance of '.Job::class);
        }
    }

    protected function verifyGetJobArgs($workerName = null, $methodName = null, $prioritize = true)
    {
        if (null !== $workerName || null !== $methodName || true !== $prioritize) {
            throw new \Exception('Unsupported');
        }
    }

    /**
     * @param string $workerName
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
            throw new \Exception("Expected \Dtc\QueueBundle\RabbitMQ\Job, got ".get_class($job));
        }
        $deliveryTag = $job->getDeliveryTag();
        $this->channel->basic_ack($deliveryTag);

        return;
    }

    public function __destruct()
    {
        if (null !== $this->channel) {
            $this->channel->close();
        }
    }
}
