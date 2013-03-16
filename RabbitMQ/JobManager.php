<?php
namespace Dtc\QueueBundle\RabbitMQ;

use Dtc\QueueBundle\Model\Job as BaseJob;
use Dtc\QueueBundle\Model\JobManagerInterface;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

$exchange = 'bench_exchange';
$queue = 'bench_queue';

$conn = new AMQPConnection(HOST, PORT, USER, PASS, VHOST);
$ch = $conn->channel();

$ch->queue_declare($queue, false, false, false, false);

$ch->exchange_declare($exchange, 'direct', false, false, false);

$ch->queue_bind($queue, $exchange);

$msg_body = <<<EOT
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz
abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyza
EOT;

$msg = new AMQPMessage($msg_body);

$time = microtime(true);

$max = isset($argv[1]) ? (int) $argv[1] : 1;

// Publishes $max messages using $msg_body as the content.
for ($i = 0; $i < $max; $i++) {
    $ch->basic_publish($msg, $exchange);
}

echo microtime(true) - $time, "\n";

$ch->basic_publish(new AMQPMessage('quit'), $exchange);

$ch->close();
$conn->close();

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

        $this->channel->queue_declare($queue, false, false, false, false);
        $this->channel->exchange_declare($exchange, 'direct', false, false, false);
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
            $beanJob = $beanJob->watch($workerName);
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
