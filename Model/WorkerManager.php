<?php
namespace Dtc\QueueBundle\Model;

use Monolog\Logger;

class WorkerManager
{
	protected $workers;
	protected $jobManager;
	protected $logger;

	public function __construct(JobManagerInterface $jobManager, Logger $logger = null) {
		$this->workers = array();
		$this->jobManager = $jobManager;
		$this->logger = $logger;
	}

	public function addWorker(Worker $worker) {
		if ($this->logger) {
			$this->logger->debug("Added worker: {$worker->getName()}");
		}

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
			if ($this->logger) {
				$this->logger->debug("No job to run");
			}

			return;        // no job to run
		}

		return $this->runJob($job);
	}

	public function runJob(Job $job) {
		try {
			$start = microtime(true);
			$worker = $this->getWorker($job->getWorkerName());
			if ($this->logger) {
				$this->logger->debug("Start: {$job->getClassName()}->{$job->getMethod()}", $job->getArgs());
			}

			call_user_func_array(array($worker, $job->getMethod()), $job->getArgs());

			// Job finshed successfuly... do we remove the job from database?
			$job->setStatus(Job::STATUS_SUCCESS);
			$job->setMessage(null);
		}
		catch (\Exception $e) {
			if ($this->logger) {
				$this->logger->debug("Failed: {$job->getClassName()}->{$job->getMethod()}\n{$e->getMessage()}");
			}

			$job->setStatus(Job::STATUS_ERROR);
			$job->setMessage($e->getMessage() . "\n" .$e->getTraceAsString());
		}

		// save Job history
		$elapsed = microtime(true) - $start;
		$job->setElapsed($elapsed);

		if ($this->logger) {
			$this->logger->debug("Finished: {$job->getClassName()}->{$job->getMethod()} in {$elapsed} micro-seconds");
			$this->logger->debug("Save job history: {$job->getId()}");
		}

		$this->jobManager->saveHistory($job);

		return $job;
	}
}
