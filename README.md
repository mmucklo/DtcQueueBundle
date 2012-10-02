DtcQueueBundle
==============

> Note: In beta, supports ODM only. Planned support for AMQP.

This bundle provides a way to queue long running jobs that should be run in backend.

- Turn any long running code into background task with a few lines
- Add workers to your application with very little effort
- Atomic operation for Job/Task
- Logs Error from worker on task
- Command to run Jobs from console
- Works with GridBundle to provide queue management

Documentation
-------------

The bulk of the documentation is stored in the `Resources/doc/index.md`

Installation
------------

Add an config entry for using document manager

	dtc_queue:
	    document_manager: default


Add MongoDB ODM setting for Job Document.

	doctrine_mongodb:
	    document_managers:
	        default:
	            mappings:
	                DtcQueueBundle:
	                    dir: Documents/
	                    type: annotation

Usage
-----

Create a worker class that will work on the background job.

	class FibonacciWorker
		extends Dtc\QueueBundle\Model\Worker
	{
		public function processFibonacci($n) {
			$feb = $this->fibonacci($n);
			file_put_conents('/tmp/fib-result.txt', "{$n}: {$feb}");
		}

		public function fibonacci($n)
		{
			if($n == 0)
				return 0; //F0
			elseif ($n == 1)
				return 1; //F1
			else
				return fibonacci($n - 1) + fibonacci($n - 2);
		}
	}

Create a DI service for the job, and tag it as a background worker.

	<service id="fibonacci_worker" class="FibonacciWorker">
	    <tag name="dat_queue.worker" />
	</service>

Create a background job.

	//$fibonacciWorker->later()->processFibonacci(20);
	//$fibonacciWorker->batchLater()->processFibonacci(20);
	$fibonacciWorker->later(90)->processFibonacci(20); // Run 90 seconds later

To Debug message queue status.

	./app/console dtc:queue_worker:count
	./app/console dtc:queue_worker:run-job {jobId}

jobId could be obtained from mongodb.

License
-------

This bundle is under the MIT license.