<?php
namespace Dtc\QueueBundle\BeanStalkd;

use Dtc\QueueBundle\Model\Job;
use Dtc\QueueBundle\Model\JobManagerInterface;

class JobManager
	implements JobManagerInterfac
{
	protected $beanstalkds;
	public function __construct(array $beanstalkds) {
		$this->beanstalkds = $beanstalkds;
	}

	public function getJobCount($workerName = null, $methodName = null) {
		if ($methodName) {
			throw new \Exception("Unsupported");
		}

		$beanstalkd = current($this->beanstalkds);
		$job = $beanstalkd
			->watch($tube)
			->reserve();
		echo $job->getData();
	}

	public function resetErroneousJobs($workerName = null, $methodName = null);
	public function pruneErroneousJobs($workerName = null, $methodName = null);
	public function getJobCount($workerName = null, $methodName = null);
	public function getStatus();
	public function deleteJob(Job $job);
	public function save(Job $job);

	//public function deleteJobById($jobId);
}
