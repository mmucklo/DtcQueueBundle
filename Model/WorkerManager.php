<?php
namespace Dtc\QueueBundle\Model;

use Monolog\Logger;

class WorkerManager
{
    protected $workers;
    protected $jobManager;
    protected $logger;

    public function __construct(JobManager $jobManager, Logger $logger = null) {
        $this->workers = array();
        $this->jobManager = $jobManager;
        $this->logger = $logger;
    }

    public function addWorker(Worker $worker) {
        $this->logger->debug("Added worker: {$worker->getName()}");

        if (isset($this->workers[$worker->getName()])) {
            throw new \Exception("{$worker->getName()} already exists in worker manager");
        }

        $this->workers[$worker->getName()] = $worker;
    }

    public function getWorker($name) {
        if (isset($this->workers[$name])) {
            return $this->workers[$name];
        }

        return null;
    }

    public function getWorkers() {
        return $this->workers;
    }

    public function run($workerName = null, $methodName = null, $prioritize = true)
    {
        $job = $this->jobManager->getJob($workerName, $methodName, $prioritize);
        if (!$job) {
            $this->logger->debug("No job to run");
            return;        // no job to run
        }

        return $this->runJob($job);
    }

    public function runJob(Job $job) {
        try {
            $worker = $this->getWorker($job->getWorkerName());
            $start = microtime(true);
            $this->logger->debug("Start: {$job->getClassName()}->{$job->getMethod()}", $job->getArgs());

            call_user_func_array(array($worker, $job->getMethod()), $job->getArgs());

            $total = microtime(true) - $start;
            $this->logger->debug("Finished: {$job->getClassName()}->{$job->getMethod()}");
            $this->logger->info("Finished: {$job->getClassName()}->{$job->getMethod()} in {$total} micro-seconds");

            // Job finshed successfuly... do we remove the job from database?
            $job->setStatus(Job::STATUS_SUCCESS);
            $job->setMessage(null);
        }
        catch (\Exception $e) {
            $this->logger->debug("Failed: {$job->getClassName()}->{$job->getMethod()} - {$e->getMessage()}");

            $job->setStatus(Job::STATUS_ERROR);
            $job->setMessage($e->getTraceAsString());
        }

        $this->logger->debug("Save Job: {$job->getId()}");
        $this->jobManager->save($job);

        return $job;
    }
}
