### 2.0
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