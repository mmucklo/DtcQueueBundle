Full Configuration
==================
```yaml
dtc_queue:
    orm:
        entity_manager: default
    odm:
        default_manager: default
    manager:
        # job - builtins: orm, odm, beanstalkd, rabbit_mq, redis
        job: odm
        # run - defaults to whatever job is set to 
        run: ~
        # job_timing - defaults to whatever run is set to
        job_timing: ~
    timings:
        # record
        #
        #  Whether to record job timings in a separate job_timings
        #  table / collection (uses same store as run_manager unless otherwise specified above)
        record: false
        #
        # timezone_offset
        #
        # If the webserver is in one timezone, but the database stores them in another timezone
        #  You may need to offset positive or negative the hours or fraction of hours between the two.
        #  For the data on the trends page to appear correctly
        #
        #  If you're not recording timings (record_timings: false), then it presently doesn't make a difference what
        #  this is set to.
        #
        timezone_offset: 0
    class:
        # Here's where you can override the classes used for job, job_archive, etc.
        #
        #  This could be usefull, say if you are using orm or odm, and want to extend the job entity class and modify
        #   the collection or table name, or what not
        job: ~
        job_archive: ~
        run: ~
        run_archive: ~
        job_timing: ~
    priority:
       #
       # max: int
       #
       #  255 is the recommended max for RabbitMQ, although Mongo/ORM
       #  could be set to INT_MAX for their platform
       max: 255
       #
       # direction
       #
       #  "desc" means 1 is high priority, "asc" means 1 is low prioirty
       #
       #  In the queue and database, priorities will always be stored
       #   in ascending order, however, (so as to sort null as the lowest)
       #  
       #  This is for the direction that a Job's setPriority() method
       #   uses, plus direction that the priority argument of such
       #   functions as later()
       direction: desc
    retry:
        max:
            # maximum total retries
            retries: 3
            # maximum total failures
            failures: 1
            # maximum total exceptions
            exceptions: 1
            # maximum total stalls
            stalls: 2
        auto:
            # auto retry on failure or exception
            failure: true
            exception: false
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
    # Redis setup - choose one of [ snc_redis | predis | phpredis ]
    redis:
        # What to prefix the redis entries with
        prefix: dtc_queue_
        snc_redis:
            # type should be one of [ predis| phpredis ]
            type: ~
            alias: ~
        predis:
            # dsn should be set, or fill in host and port in connection_parameters, but not both
            dsn: ~
            connection_parameters:
                scheme: tcp
                host: ~
                port: ~
                path: ~
                database: ~
                password: ~
                async: false
                persistent: false
                timeout: 5.0
                read_write_timeout: ~
                alias: ~
                weight: ~
                iterable_multibulk: false
                throw_errors: true
        phpredis:
            # minimum fill host and port if needed
            host: ~
            port: ~
            timeout: ~
            retry_interval: ~
            read_timeout: ~
            auth: ~

```
