<?php
namespace Dtc\QueueBundle\RabbitMQ;

use Dtc\QueueBundle\Model\Job as BaseJob;
use Dtc\QueueBundle\Model\JobManagerInterface;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class JobManager
    implements JobManagerInterface
{
    protected $channel;
    protected $connection;

    public function __construct(AMQPConnection $connection) {
        $this->connection = $connection;
        $this->channel = $connection->channel();;
    }

    public function save($job) {
        $queue = $job->getWorkerName();
        $exchange = null; 	// User default exchange

        $this->channel->queue_declare($queue, false, true, false, false);
        $this->channel->exchange_declare($exchange, 'direct', false, true, false);
        $this->channel->queue_bind($queue, $exchange);

        $msg = new AMQPMessage($job->toMessage());
        $jobId = $this->channel->basic_publish($msg);

        $job->setId($jobId);
        return $job;
    }

    public function getJob($workerName = null, $methodName = null, $prioritize = true)
    {
        if ($methodName) {
            throw new \Exception("Unsupported");
        }

        $beanJob = $this->beanstalkd;
        if ($workerName) {
            $this->channel->basic_consume($workerName, '', false, true, false, false);
        }

        $beanJob = $beanJob->reserve();
        if ($beanJob) {
            if ($this->isBuryOnGet) {
                $this->beanstalkd->bury($beanJob);
            }

            $job = new Job();
            $job->fromMessage($beanJob->getData());
            $job->setId($beanJob->getId());
            return $job;
        }
    }

    public function deleteJob($job) {
        $this->beanstalkd
            ->delete($job);
    }

    // Save History get called upon completion of the job
    public function saveHistory($job) {
        if ($job->getStatus() === BaseJob::STATUS_SUCCESS) {
            $this->beanstalkd
                ->delete($job);
        }
        else {
            $this->beanstalkd
                ->bury($job);
        }
    }

    public function getJobCount($workerName = null, $methodName = null) {
        if ($methodName) {
            throw new \Exception("Unsupported");
        }

        if ($workerName) {
            throw new \Exception("Unsupported");
        }
    }

    public function resetErroneousJobs($workerName = null, $methodName = null) {
        throw new \Exception("Unsupported");
    }

    public function pruneErroneousJobs($workerName = null, $methodName = null) {
        throw new \Exception("Unsupported");
    }

    public function getStatus() {
        throw new \Exception("Unsupported");
    }
}
