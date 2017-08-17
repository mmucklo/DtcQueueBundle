<?php

namespace Dtc\QueueBundle\RabbitMQ;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Message\AMQPMessage;

class JobManager implements JobManagerInterface
{
    /** @var AMQPChannel */
    protected $channel;

    /** @var AbstractConnection */
    protected $connection;
    protected $queueArgs;
    protected $exchangeArgs;

    protected $channelSetup = false;

    public function setExchangeArgs($exchange, $type, $passive, $durable, $autoDelete)
    {
        $this->exchangeArgs = func_get_args();
    }

    public function setQueueArgs($queue, $passive, $durable, $exclusive, $autoDelete)
    {
        $this->queueArgs = func_get_args();
    }

    public function setAMQPConnection(AbstractConnection $connection)
    {
        $this->connection = $connection;
        $this->channel = $connection->channel();
    }

    protected function setupChannel()
    {
        if (!$this->channelSetup) {
            call_user_func_array([$this->channel, 'exchange_declare'], $this->exchangeArgs);
            call_user_func_array([$this->channel, 'queue_declare'], $this->queueArgs);
            $this->channel->queue_bind($this->queueArgs[0], $this->exchangeArgs[0]);
            $this->channelSetup = true;
        }
    }

    public function save(\Dtc\QueueBundle\Model\Job $job)
    {
        $this->setupChannel();
        $msg = new AMQPMessage($job->toMessage());
        $this->channel->basic_publish($msg, $this->exchangeArgs[0]);

        return $job;
    }

    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {
        if ($methodName) {
            throw new \Exception('Unsupported');
        }

        $this->setupChannel();
        $message = $this->channel->basic_get($this->queueArgs[0]);
        if ($message) {
            $job = new Job();
            $job->fromMessage($message->body);
            $job->setId($message->delivery_info['delivery_tag']);

            return $job;
        }

        return null;
    }

    public function deleteJob(\Dtc\QueueBundle\Model\Job $job)
    {
        throw new \Exception('unsupported');
    }

    // Save History get called upon completion of the job
    public function saveHistory(\Dtc\QueueBundle\Model\Job $job)
    {
        $deliveryTag = $job->getId();
        $this->channel->basic_ack($deliveryTag);

        return;
    }

    public function __destruct()
    {
        $this->channel->close();
    }

    public function getJobCount($workerName = null, $methodName = null)
    {
        if ($methodName) {
            throw new \Exception('Unsupported');
        }

        if ($workerName) {
            throw new \Exception('Unsupported');
        }
    }

    public function resetErroneousJobs($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null)
    {
        throw new \Exception('Unsupported');
    }

    public function getStatus()
    {
        throw new \Exception('Unsupported');
    }
}
