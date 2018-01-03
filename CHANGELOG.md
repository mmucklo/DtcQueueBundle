### 4.0.0
   * Class Moves
      * All Manager classes and interfaces into their own Manager directory
   * New STATUS_FAILURE
      * If you return \Dtc\QueueBundle\Model\Worker::RESULT_FAILURE from your Worker's function, it will be recorded as
         a failure, not an error
   * BaseJob::STATUS_ERROR => BaseJob::STATUS_EXCEPTION
      * Exceptions are now recorded as such.
      * Exception information is recorded in the end of the message of the 
   * Access signatures change
      * $jobManager made private in Worker base class.  Still accessible through getJobManager()
   * Access to Current Job
      * $this->getCurrentJob() will return the current Job object during a worker's method invocation.
         * This may be useful if you want to record some message by calling $this->getCurrentJob()->setMessage('some message');
         * However only Doctrine Jobs are persisted on SUCCESS         
   * Worker
      * $jobClass removed
         * $jobClass member variable, and getJobClass() / setJobClass() functions removed.
      * $jobManager made private - accesss methods exist
   * Changed STATUS_ERROR to STATUS_EXCEPTION
   * Auto Retry
      * Jobs that fail will automatically be retried a certain number of times, by being re-enqueued
      * Tunable Defaults
         * The number of max retries is tunable
         * The number of max exceptions, failures is tunable
         * The number of permitted stalls is also tunable
         * Exceptions can be auto-retried, but by default are not.
   * Refactoring
      * JobManager base class heirarchy has been refactored.
         * Supports retryable jobs in all JobManager types
      * Job base class heirarchy refactored
         * There's now a DoctrineJob which alone contains the Lock/unlock fields as it's only relevant to that Job type
   * ODM/ORM changes
      * stalledCount / stalled_count renamed to stalls
      * errorCount / error_count renamed to exceptions
      * max_errors renamed to max_exceptions
      * max_stalled renamed to max_stalls
      * removed locked and locked_at (redundant)
   * Bug fix - add Compiler passes for RabbitMq, and Beanstalk
### 3.1.2
   * Fix a bug in timings by minute for ORM
   * Fix a bug in ODM timings
   * Add more tests
### 3.1.1
   * Fix a bug in LiveJobsGridSource for ORM
   * Refactored out some Archive logic into ArchivableJobManager
   * Added more tests
### 3.1.0
   * Rename Live Jobs to Waiting Jobs, and add Running Jobs tab
   * New Archive button on the Waiting Jobs administration page to archive all jobs at once
   * Fix a bug when running doctrine orm and odm simulatenously
### 3.0.4
   * Refactoring out the TrendsController from the QueueController, ran php-cs-fixer
### 3.0.2
   * Updates to trends page, GridBundle version, WorkerCompilerTest
### 3.0.1
   * Bump DtcGridBundle requirement
### 3.0.0
   * Symfony 4 support
   * Enabled Garbage collection during usage of RunCommand, but allow it to be disabled via a command-line option --disable-gc
   * Changed some command-line parameters to have "-" instead of "_" (see [UPGRADING-3.0.md](UPGRADING-3.0.md))
   * Added a "status" column to JobTiming, plus updated the index for ORM
   * Updated the trends graph to show multiple statuses
### 2.7.13
   * fixed wrong template
### 2.7.12
   * fix templating issue by being explicit
### 2.7.11
   * back out object manager setting to make SensioLabs Insight happier..
### 2.7.10
   * Make services public for now
### 2.7.9
   * Add a method to set the object manager for RunManager and JobManager
### 2.7.8
   * Fix ODM (mongo) status function
### 2.7.7
   * Updates to the trends page
### 2.7.6
   * Fix bug with LiveJob Query
### 2.7.5
   * Fix bug with Job count query
### 2.7.4
   * Fix the dependency issue pt.2
### 2.7.3
   * Fix the dependency issue
### 2.7.2
   * Attempt to fix a dependency issue
### 2.7.1
   * Fix a bug in mongo Job Manager
   * Travis build fixes for RunManager
   * Travis build for 7.2
### 2.7.0
   * Administrative updates
      * Add a header to admin pages
      * Show Live Jobs for ODM and ORM according to actual jobs query (won't show expired jobs)
      * Separate page for showing "All" Jobs + "Archived" Jobs
      * Added Workers list page
      * Added Trends page
   * Fixed a bug where run_manager being different than default_manager could cause a problem
### 2.6.7
   * Remove transactions from ORM to avoid lock contention issues
   * Add more testing
### 2.6.6
   * Another bug with PHP >= 7
### 2.6.5
   * 2nd bug with runId in ORM
### 2.6.4
   * bug with runId in ORM
### 2.6.3
   * Remove transactions from getJob() to avoid locking contention
### 2.6.2
   * Prevent infinite looping if commits keep failing
### 2.6.1
   * Fix a bug in ORM\JobManager
### 2.6.0
   * Fix a race-condition with batching in ORM and ODM
   * Fix a bug with prioritization not being done right with ORM and ODM
   * Add more testing, fix extended configuration for RabbitMQ, and make it work properly
   * Fixes from SensioLabs Insights
   * May have been an issue with instantiating a rabbit_mq / beanstalkd based queue
   * Schema Update:
      * Added a new column to Run: currentJobId
         * tracks the id of the currently running job (null if nothing)
### 2.5.3
   * Fix a bug in queue.yml
### 2.5.2
   * Add a test
   * Fix a few issues from Sensio Insight
   * PR#21 - Support sensio/framework-extra-bundle:5.*
### 2.5.1
   * Fix PruneCommand output in certain cases
### 2.5.0
   * Fix bug with archiving of Runs
   * Add a prune for stalled runs
### 2.4.0
   * Fix bug with bad annotation
   * Fix priorities - now there's a max priority and priority direction setting in configuration
   * Added tests for Priorities for ODM / ORM, plus RabbitMQ
### 2.3.3
   * Fix build
### 2.3.2
   * Add initial testing for WorkerCompilerPass
### 2.3.1
   * Fix bug with JobTiming class
### 2.3.0
   * Added a new JobTiming entity/document to separately track timing information for each run
   * Fix a bug in beanstalkd and rabbitmq JobManager classes where expired jobs could cause a loop
   * Added more unit tests
   * Fixed a few bugs with rabbitmq configuration options that weren't able to be passed through
### 2.2.0
   * Create a RunManager for tracking Runs
   * Add a new config parameter run_manager so that a job queue can have a separate store-type for Runs
   * Unit test commands
   * Added ability to prune old Run entries
   * Refactoring of RunCommand and other areas to hopefully improve or at least maintain code quality
   * Updated README
   * Fixed issue #15 - should make things work again on Symfony 2
### 2.1.1
   * Upped GridBundle requirement
   * Refactoring and tests
   * Updated getStatus method in BaseJobManager's present derived classes with new statuses
### 2.1.0
   * Fix bug in pruning of stalled jobs
   * Refactoring of updatedAt and createdAt out of Model\Job
   * Created a new subclass called RetryableJob
      * Add a max retries
      * Add a max stalled
      * Add a max error
   * Fixed unit tests for ORM
   * Made job methods chainable
### 2.0.1
   * Update precision for expiresAt for beanstalkd and RabbitMQ
   * Fixed a bug with batchLater($delay) - where $delay was interpreted as a unixtimestamp, instead of the seconds to delay
### 2.0.0
   * Rename of core commands
       * Old: dtc:queue_worker:*
       * New: dtc:queue:*
   * RunCommand (dtc:queue:run):
       * Refined options:
           * -d [--duration - exit after time elapses beyond this many seconds]
           * -m [--max_count - maximum number of jobs to process before existing]
              * This was formerly --total_jobs / -t
           * -l [--logger service name e.g. monolog.logger.dtc_queue]
           * -s [--nano_sleep (nanoseconds) to sleep when there's no jobs in the queue]
           * -t [--timeout (in seconds) for the process]
           * -i [--id of job to run]
       * Removed redundant options
           * --total_jobs / -t -> replaced by --max_count / -m
           * --id -> --id / -i
           * --job_id -> removed in favor of --id / -i
   * PruneCommand (dtc:queue:prune):
       * Now takes an argument:
           * error|expired|old
              * error: prunes errored jobs (from archive queue)
              * expired: prunes expired jobs (from live queue)
              * old: prunes archived jobs older than:
                  * --older (\d+)([d|m|y|h|i|s]){0,1}
                  *  e.g. --older 1m
                  *       --older 1y
                  *  e.g. --older 36h
                  *  e.g. --older 86400s
   * ResetJ
   * New: logs each run into a specified datastore
   * New: ORM-based storage
   * New: Job archive table (for completed and/or errored jobs)
   * Config change: renamed mongo Documents to Document - you may need to update config.yml in the mongodb section:

Before
```yaml
doctrine_mongodb:
    connections:
        default:
            server: "%mongodb_server%"
            options: {}
    default_database: symfony
    document_managers:
        default:
            auto_mapping: true
            mappings:
                DtcQueueBundle:
                    dir: Documents/
                    type: annotation
```
After
```yaml
doctrine_mongodb:
    connections:
        default:
            server: "%mongodb_server%"
            options: {}
    default_database: symfony
    document_managers:
        default:
            auto_mapping: true
            mappings:
                DtcQueueBundle:
                    dir: Document/
                    type: annotation
```
   * changed mongo database from queue to dtc_queue
   * renamed when to whenAt
   * renamed expires to expiresAt
   * Added new ORM JobManager
   * For MongoDB and ORM jobs, when the job is retrieved, change status to 'running'.
       * Fixes a race condition where jobs could be added in batch mode that weren't getting processed due to a stuck 'locked' job that was still in status 'new'
   * Changed routing
       * New routing.yml file to reference
       * New standard routing prefix "/dtc_queue/"
Before:
```yaml
queue:
    resource: "@DtcQueueBundle/Controller/QueueController.php"
    prefix:  /queue/
    type: annotation
```
After:
```yaml
queue:
    resource: '@DtcQueueBundle/Resources/config/routing.yml'
```
