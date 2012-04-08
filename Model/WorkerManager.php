<?php
namespace Dtc\QueueBundle\Model;

class WorkerManager
{
    protected $workers;
    protected $jobManager;
    protected $isDebug;

    public function __construct(JobManager $jobManager, $isDebug = true) {
        $this->workers = array();
        $this->jobManager = $jobManager;
        $this->isDebug = $isDebug;
    }

    public function addWorker(Worker $worker) {
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
            return;        // no job to run
        }

        $e = null;
        try {
            $worker = $this->getWorker($job->getWorkerName());
            call_user_func_array(array($worker, $job->getMethod()), $job->getArgs());

            // Job finshed successfuly... do we remove the job from database?
            $job->setStatus(Job::STATUS_SUCCESS);
            $job->setMessage(null);
        }
        catch (\Exception $e) {
            $job->setStatus(Job::STATUS_ERROR);
            $job->setMessage($e->getTraceAsString());
        }

        $this->jobManager->save($job);

        if ($this->isDebug && $e)
        {
            throw $e;
        }

        return $job;
    }
}
