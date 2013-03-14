DtcQueueBundle
==============

> Note: In beta, supports ODM only. Planned support for AMQP.

This bundle provides a way to queue long running jobs that should be
run in backend.

- Turn any long running code into background task with a few lines
- Add workers to your application with very little effort
- Atomic operation for Job/Task
- Logs Error from worker on task
- Command to run Jobs from console
- Works with GridBundle to provide queue management

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
	    extends \Dtc\QueueBundle\Model\Worker
	{
	    private $filename;
	    public function __construct() {
	        $this->filename = '/tmp/fib-result.txt';
	        $this->jobClass = 'Dtc\QueueBundle\Model\Job';
	    }

	    public function fibonacciFile($n) {
	        $feb = $this->fibonacci($n);
	        file_put_contents($this->filename, "{$n}: {$feb}");
	    }


	    public function fibonacci($n)
	    {
	        if($n == 0)
	            return 0; //F0
	        elseif ($n == 1)
	            return 1; //F1
	        else
	            return $this->fibonacci($n - 1) + $this->fibonacci($n - 2);
	    }

	    public function getName() {
	        return 'fibonacci';
	    }

	    public function exceptionThrown() {
	        throw new \Exception('error...');
	    }

	    public function getFilename()
	    {
	        return $this->filename;
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

Running as upstart service:
---------------------------

1. Create the following file in /etc/init/.

	# /etc/init/queue.conf

	author "David Tee"
	description "Starts QVC queue worker server"

	respawn
	start on startup solr_push

	script
	        /{path to}/console dtc:queue_worker:run >> /var/logs/queue.log 2>&1
	end script

2. Reload config: sudo initctl reload-configuration
3. Start the script: sudo start queue

Admin
-----

You can register admin routes to see queue status. In your routing.yml file, add the following:

	queue:
	    resource: "@DtcQueueBundle/Controller/QueueController.php"
	    prefix:  /queue/
	    type: annotation

License
-------

This bundle is under the MIT license.