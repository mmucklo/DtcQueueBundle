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
