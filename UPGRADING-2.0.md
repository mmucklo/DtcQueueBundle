# 2.0

## Config Changes

   * If using MongoDB, change the path to the documents directory:

Before:   
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

After:
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

## Routing Changes
(only applies if monitoring jobs)

   * Entry in routing.yml was simplified:

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

   * /queue/jobs/ -> /dtc_queue/jobs
   * New:
      * /queue/runs

## Schema Changes

   This largely applies only to Mongo
   
   * Database name has been changed from queue to dtc_queue
      * You'll want to change the database name during a maintenance window if you want to keep the old /existing jobs
   * when -> whenAt field name change
      * For mongo, this should be transparent due to the @AlsoLoad annotation on BaseJob.
   * New JobArchive collection

## Console command changes
   * Rename of core commands
       * Old: dtc:queue_worker:*
       * New: dtc:queue:*
   * RunCommand:
       * Refined options:
           * -d [--duration - exit after time elapses beyond this many seconds]
           * -m [--max_count - maximum number of jobs to process before existing]
              * This was formerly '--total_jobs' / -t
           * -l [--logger service name e.g. monolog.logger.dtc_queue]
           * -s [--nano_sleep (nanoseconds) to sleep when there's no jobs in the queue]
           * -t [--timeout (in seconds) for the process]
           * -i [--id of job to run]
       * Removed redundant options
           * '--total_jobs' / -t -> replaced by -m
           * '--id' -> '--id' / -i
           * '--job_id' -> removed in favor of 'id' / i
