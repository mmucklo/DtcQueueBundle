<?php

namespace Dtc\QueueBundle\Manager;

use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Dtc\QueueBundle\Exception\DuplicateWorkerException;
use Dtc\QueueBundle\Model\BaseJob;
use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobTiming;
use Dtc\QueueBundle\Model\Worker;
use Dtc\QueueBundle\Util\Util;
use Psr\Log\LoggerInterface;

class WorkerManager
{
    protected $workers;
    protected $jobManager;

    /** @var LoggerInterface */
    protected $logger;
    protected $eventDispatcher;
    protected $logFunc;

    public function __construct(JobManagerInterface $jobManager, EventDispatcher $eventDispatcher)
    {
        $this->workers = array();
        $this->jobManager = $jobManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Worker $worker
     *
     * @throws DuplicateWorkerException
     */
    public function addWorker(Worker $worker)
    {
        if ($this->logger) {
            $this->logger->debug(__METHOD__." - Added worker: {$worker->getName()}");
        }

        if (isset($this->workers[$worker->getName()])) {
            throw new DuplicateWorkerException("{$worker->getName()} already exists in worker manager");
        }

        $this->workers[$worker->getName()] = $worker;
    }

    public function getWorker($name)
    {
        if (isset($this->workers[$name])) {
            return $this->workers[$name];
        }

        return null;
    }

    public function getWorkers()
    {
        return $this->workers;
    }

    public function setLoggingFunc(callable $callable)
    {
        $this->logFunc = $callable;
    }

    public function log($level, $msg, array $context = [])
    {
        if ($this->logFunc) {
            call_user_func_array($this->logFunc, [$level, $msg, $context]);

            return;
        }

        if ($this->logger) {
            $this->logger->$level($msg, $context);

            return;
        }
    }

    /**
     * @param null $workerName
     * @param null $methodName
     * @param bool $prioritize
     *
     * @return Job|null
     */
    public function run($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        $job = $this->jobManager->getJob($workerName, $methodName, $prioritize, $runId);
        if (!$job) {
            return null; // no job to run
        }

        return $this->runJob($job);
    }

    /**
     * @param array $payload
     * @param Job   $job
     */
    protected function handleException(array $payload, Job $job)
    {
        $exception = $payload[0];
        $exceptionMessage = get_class($exception)."\n".$exception->getCode().' - '.$exception->getMessage()."\n".$exception->getTraceAsString();
        $this->log('debug', "Failed: {$job->getClassName()}->{$job->getMethod()}");
        $job->setStatus(BaseJob::STATUS_EXCEPTION);
        $message = $job->getMessage();
        if (null !== $message) {
            $message .= "\n\n";
        } else {
            $message = $exceptionMessage;
        }

        $job->setMessage($message);
        $this->jobManager->getJobTimingManager()->recordTiming(JobTiming::STATUS_FINISHED_EXCEPTION);
    }

    public function processStatus(Job $job, $result)
    {
        if (Worker::RESULT_FAILURE === $result) {
            $job->setStatus(BaseJob::STATUS_FAILURE);
            $this->jobManager->getJobTimingManager()->recordTiming(JobTiming::STATUS_FINISHED_FAILURE);

            return;
        }
        $job->setStatus(BaseJob::STATUS_SUCCESS);
        $this->jobManager->getJobTimingManager()->recordTiming(JobTiming::STATUS_FINISHED_SUCCESS);
    }

    public function runJob(Job $job)
    {
        $event = new Event($job);
        $this->eventDispatcher->dispatch(Event::PRE_JOB, $event);

        $start = microtime(true);
        try {
            /** @var Worker $worker */
            $worker = $this->getWorker($job->getWorkerName());
            $this->log('debug', "Start: {$job->getClassName()}->{$job->getMethod()}", $job->getArgs());
            $job->setStartedAt(Util::getMicrotimeFloatDateTime($start));
            $job->setMessage(null);
            $worker->setCurrentJob($job);
            $result = call_user_func_array(array($worker, $job->getMethod()), $job->getArgs());
            $this->processStatus($job, $result);
        } catch (\Throwable $exception) {
            $this->handleException([$exception], $job);
        } catch (\Exception $exception) {
            $this->handleException([$exception], $job);
        }

        // save Job history
        $elapsed = microtime(true) - $start;
        $job->setFinishedAt(Util::getMicrotimeDateTime());
        $job->setElapsed($elapsed);

        $this->log('debug', "Finished: {$job->getClassName()}->{$job->getMethod()} in {$elapsed} seconds");
        $this->log('debug', "Save job history: {$job->getId()}");

        $this->jobManager->saveHistory($job);
        $this->eventDispatcher->dispatch(Event::POST_JOB, $event);

        return $job;
    }
}
