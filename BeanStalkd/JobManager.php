<?php
namespace Dtc\QueueBundle\BeanStalkd;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;

class JobManager
	implements JobManagerInterfac
{
	protected $beanstalkd;
	public function __construct(\Pheanstalk_Pheanstalk $beanstalkd) {
		$this->beanstalkd = $beanstalkd;
	}

	public function getJobCount($workerName = null, $methodName = null) {
		if ($methodName) {
			throw new \Exception("Unsupported");
		}

		if ($workerName) {
			$job = $this->beanstalkd
				->watch($workerName)
				->reserve();
			echo $job->getData();
		}
	}

	public function resetErroneousJobs($workerName = null, $methodName = null) {
		throw new \Exception("Unsupported");
	}

	public function pruneErroneousJobs($workerName = null, $methodName = null) {
		throw new \Exception("Unsupported");
	}

	public function getJobCount($workerName = null, $methodName = null) {
		throw new \Exception("Unsupported");
	}

	public function getJob($workerName = null, $methodName = null, $prioritize = true)
	{
		if ($methodName) {
			throw new \Exception("Unsupported");
		}

		if ($workerName) {
			$job = $this->beanstalkd
				->watch($workerName)
				->reserve();
			echo $job->getData();
		}
	}

	public function getStatus() {
		throw new \Exception("Unsupported");
	}

	public function deleteJob(Job $job) {
		$this->beanstalkd
			->delete($job->getId());
	}

	public function save(Job $job) {
		$job = $this->beanstalkd
			->watch($workerName)
			->reserve();

		// To handle duplicate, there must be a duplicate job manager
		echo $job->getData();
		// convert job to Job class
	}

	//public function deleteJobById($jobId);
}
