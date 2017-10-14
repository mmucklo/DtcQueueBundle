<?php

namespace Dtc\QueueBundle\RabbitMQ;

use Dtc\QueueBundle\Model\AbstractJobManager;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;

class JobManager extends AbstractJobManager
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
    protected $maxPriority;

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
        $this->exchangeArgs = func_get_args();
    }

    /**
     * @param string $queue
     * @param bool   $passive
     * @param bool   $durable
     * @param bool   $exclusive
     * @param bool   $autoDelete
     * @param int    $maxPriority
     */
    public function setQueueArgs($queue, $passive, $durable, $exclusive, $autoDelete, $maxPriority)
    {
        $arguments = func_get_args();
        array_pop($arguments); // Pop off max priority

        $this->queueArgs = $arguments;
        if (!ctype_digit(strval($maxPriority))) {
            throw new \Exception('Max Priority needs to be a non-negative integer');
        }
        if (strval(intval($maxPriority)) !== strval($maxPriority)) {
            throw new \Exception('Priority is higher than '.PHP_INT_MAX);
        }
        $this->maxPriority = $maxPriority;
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

    public function setupChannel()
    {
        if (empty($this->queueArgs)) {
            throw new \Exception(__METHOD__.': queue args need to be set via setQueueArgs(...)');
        }
        if (emtpy($this->exchangeArgs)) {
            throw new \Exception(__METHOD__.': exchange args need to be set via setExchangeArgs(...)');
        }

        if (!$this->channelSetup) {
            call_user_func_array([$this->channel, 'exchange_declare'], $this->exchangeArgs);
            if ($this->maxPriority) {
                array_push($this->queueArgs, false);
                array_push($this->queueArgs, ['x-max-priority' => ['I', intval($this->maxPriority)]]);
            }
            call_user_func_array([$this->channel, 'queue_declare'], $this->queueArgs);
            $this->channel->queue_bind($this->queueArgs[0], $this->exchangeArgs[0]);
            $this->channelSetup = true;
        }
    }

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        $this->setupChannel();
        if (!$job->getId()) {
            $job->setId(uniqid($this->hostname.'-'.$this->pid, true));
        }

        if (null !== ($priority = $job->getPriority()) && !$this->maxPriority) {
            throw new \Exception('This queue does not support priorities');
        }

        $msg = new AMQPMessage($job->toMessage());

        if ($this->maxPriority) {
            $priority = null === $priority ? 0 : $this->maxPriority - $priority;
            $msg->set('priority', $priority);
        }
        $this->channel->basic_publish($msg, $this->exchangeArgs[0]);

        return $job;
    }

    /**
     * @param string $workerName
     */
    public function getJob($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        if ($methodName) {
            throw new \Exception('Unsupported');
        }

        $this->setupChannel();

        $expiredJob = false;
        do {
            $message = $this->channel->basic_get($this->queueArgs[0]);
            if ($message) {
                $job = new Job();
                $job->fromMessage($message->body);
                $job->setRunId($runId);

                if (($expiresAt = $job->getExpiresAt()) && $expiresAt->getTimestamp() < time()) {
                    $expiredJob = true;
                    $this->channel->basic_nack($message->delivery_info['delivery_tag']);
                    continue;
                }
                $job->setDeliveryTag($message->delivery_info['delivery_tag']);

                return $job;
            }
        } while ($expiredJob);

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
        $this->channel->close();
    }
}
