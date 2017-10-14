<?php

namespace Dtc\QueueBundle\Model;

use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventDispatcher;
use Monolog\Logger;

class WorkerManager
{
    protected $workers;
    protected $jobManager;
    protected $logger;
    protected $eventDispatcher;
    protected $logFunc;

    public function __construct(JobManagerInterface $jobManager, EventDispatcher $eventDispatcher, Logger $logger = null)
    {
        $this->workers = array();
        $this->jobManager = $jobManager;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function addWorker(Worker $worker)
    {
        if ($this->logger) {
            $this->logger->debug("Added worker: {$worker->getName()}");
        }

        if (isset($this->workers[$worker->getName()])) {
            throw new \Exception("{$worker->getName()} already exists in worker manager");
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
     * @return null|Job
     */
    public function run($workerName = null, $methodName = null, $prioritize = true, $runId = null)
    {
        $job = $this->jobManager->getJob($workerName, $methodName, $prioritize, $runId);
        if (!$job) {
            return null; // no job to run
        }

        return $this->runJob($job);
    }

    public function runJob(Job $job)
    {
        $event = new Event($job);
        $this->eventDispatcher->dispatch(Event::PRE_JOB, $event);

        $start = microtime(true);
        $handleException = function ($exception) use ($job) {
            /** @var \Exception $exception */
            $exceptionMessage = get_class($exception)."\n".$exception->getCode().' - '.$exception->getMessage()."\n".$exception->getTraceAsString();
            $this->log('debug', "Failed: {$job->getClassName()}->{$job->getMethod()}");
            $job->setStatus(BaseJob::STATUS_ERROR);
            $job->setMessage($exceptionMessage);
        };
        try {
            $worker = $this->getWorker($job->getWorkerName());
            $this->log('debug', "Start: {$job->getClassName()}->{$job->getMethod()}", $job->getArgs());
            $job->setStartedAt(new \DateTime());
            call_user_func_array(array($worker, $job->getMethod()), $job->getArgs());

            // Job finshed successfuly... do we remove the job from database?
            $job->setStatus(BaseJob::STATUS_SUCCESS);
            $job->setMessage(null);
        } catch (\Throwable $exception) {
            $handleException($exception);
        } catch (\Exception $exception) {
            $handleException($exception);
        }

        // save Job history
        $elapsed = microtime(true) - $start;
        $job->setFinishedAt(new \DateTime());
        $job->setElapsed($elapsed);

        $this->log('debug', "Finished: {$job->getClassName()}->{$job->getMethod()} in {$elapsed} micro-seconds");
        $this->log('debug', "Save job history: {$job->getId()}");

        $this->jobManager->saveHistory($job);
        $this->eventDispatcher->dispatch(Event::POST_JOB, $event);

        return $job;
    }
}
