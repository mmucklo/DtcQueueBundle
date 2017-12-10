Full Configuration
==================
```yaml
dtc_queue:
    document_manager: default
    entity_manager: default
    # default_manager
    #
    #  builtins: orm, odm, beanstalkd, rabbit_mq
    default_manager: odm
    #
    # run_manager: can be set to any of the same options as
    #
    #  "default_manager", defaults to what ever default_manager is set to
    run_manager: ~
    #
    # job_timing_manager: can be set to any of the same options as
    #
    #  "default_manager", defaults to what ever run_manager is set to
    job_timing_manager: ~
    # You can override the various base Job classes here:
    class_job: ~
    class_job_archive: ~
    class_run: ~
    class_run_archive: ~
    class_job_timing: ~
    #
    # record_timings
    #
    #  Whether to record job timings in a separate job_timings
    #  table / collection (uses same store as run_manager)
    record_timings: false
    #
    # prioirty_max: int
    #
    #  255 is the recommended max for RabbitMQ, although Mongo/ORM
    #  could be set to INT_MAX for their platform
    priority_max: 255
    #
    # priority_direction
    #
    #  "desc" means 1 is high priority, "asc" means 1 is low prioirty
    #
    #  In the queue and database, priorities will always be stored
    #   in ascending order, however, (so as to sort null as the lowest)
    #  
    #  This is for the direction that a Job's setPriority() method
    #   uses, plus direction that the priority argument of such
    #   functions as later()
    priority_direction: desc
    admin:
        chartjs: https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.1/Chart.bundle.min.js
    beanstalkd:
        host: ~
        tube: ~
    rabbit_mq:
        host: ~
        port: ~
        user: ~
        password: ~
        vhost: "/"
        ssl: false
        options: ~
        ssl_options: ~
        queue_args:
            queue: dtc_queue
            passive: false
            durable: true
            exclusive: false
            auto_delete: false
       exchange_args:
            exchange: dtc_queue_exchange
            type: direct
            passive: false
            durable: true
            auto_delete: false
```
