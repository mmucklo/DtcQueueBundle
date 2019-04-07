DtcQueueBundle
==============

[![Build Status](https://secure.travis-ci.org/mmucklo/DtcQueueBundle.png?branch=master)](http://travis-ci.org/mmucklo/DtcQueueBundle)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mmucklo/DtcQueueBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mmucklo/DtcQueueBundle/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/mmucklo/DtcQueueBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/mmucklo/DtcQueueBundle/?branch=master)
[![SymfonyInsight](https://insight.symfony.com/projects/a417419a-2d04-43eb-b7a2-1a04a4dfcc8e/mini.svg)](https://insight.symfony.com/projects/a417419a-2d04-43eb-b7a2-1a04a4dfcc8e)

> Allow symfony developers to create background job as easily as: `$worker->later()->process(1,2,3)`

### 4.0 Release

See [changes](CHANGELOG.md)

Upgrading from 3.0: [see UPGRADING-4.0.md](UPGRADING-4.0.md)

Supported Queues
----------------

- MongoDB via Doctrine-ODM
- Mysql / Doctrine 2 supported databases via Doctrine-ORM
- Beanstalkd via pheanstalk
- RabbitMQ via php-amqplib
- Redis support via Predis or PhpRedis, or through SncRedisBundle

![Trends](/Resources/doc/images/trends-example.png?raw=true "DtcQueue Trends")

Introduction
------------

This bundle provides a way to easily create and manage queued background jobs

**Basic Features:**

- Ease of Use
   - Kickoff background tasks with a line of code or two
   - Easily add background worker services
      - Turn any code into background task with a few lines
- Atomic operation for jobs
   - For ORM-based queues this is done _without_ relying on transactions.
- Admin interface
   - Web-based Admin interface with an optional performance graph
- Command Line Interface
   - Commands to run, manage and debug jobs from console
- Job Archival
   - ORM and ODM managers have built-in job-archival for finished jobs
- Logs errors from worker
- Various safety checks for things such as stalled jobs, exception jobs
   - Allows for reseting stalled and exception jobs via console commands
- Built in Event Dispatcher

**Job-specific Features:**
   - Auto-retry on failure, exception
      * If a job exits with a failure code, it can auto-retry
      * Same for Exception if desired
   - Priority
      * Jobs can have levels of priority so that higher priority jobs can get processed first even if they were added
      *  to the queue later.
   - Future Jobs (ODM / ORM / Redis)
      * Jobs can be scheduled to run at some time in the future
   - Batch
      * Jobs can be "batched" so that only one job runs, even if multiple are queued of the same type
   - Expires
      * Jobs can have an "expires" time so that they wont run after a certain point
         * (useful if the queue gets backed up and a job is worthless after a certain time)
   - Stalls (ODM / ORM)
      * Jobs that crash the interpreter, or get terminated for some other reason can be detected
         * These can be re-queued to run in the future.

Installation
------------

### Symfony 2/3

[see /Resources/doc/symfony2-3.md](/Resources/doc/symfony2-3.md)

### Symfony 4

[see /Resources/doc/symfony4.md](/Resources/doc/symfony4.md)

Usage
-----

Create a worker class that will work on the background job.

Example:
   * __src/Worker/FibonacciWorker.php:__ (symfony 4)
   * __src/AppBundle/Worker/FibonacciWorker.php:__ (symfony 2/3)
```php
<?php
namespace App\Worker; // for symfony 2/3, the namespace would typically be AppBundle\Worker

class FibonacciWorker
    extends \Dtc\QueueBundle\Model\Worker
{
    private $filename;
    public function __construct() {
        $this->filename = '/tmp/fib-result.txt';
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

    public function getFilename()
    {
        return $this->filename;
    }
}
```

Create a DI service for the job, and tag it as a background worker.

##### YAML:

__Symfony 4 and 3.3, 3.4:__
```yaml
services:
    # for symfony 3 the class name would likely be AppBundle\Worker\FibonacciWorker
    App\Worker\FibonacciWorker:
        # public: false is possible if you completely use DependencyInjection for access to the service
        public: true
        tags:
            - { name: "dtc_queue.worker" }
```

__Symfony 2, and 3.0, 3.1, 3.2:__

```yaml
services:
    app.worker.fibonacci:
        class: AppBundle\Worker\FibonacciWorker:
        tags:
            - { name: "dtc_queue.worker" }
```

##### XML:
```xml
<services>
	<!-- ... -->
	<service id="fibonacci_worker" class="FibonacciWorker">
	    <tag name="dtc_queue.worker" />
	</service>
	<!-- ... -->
</services>
```


#### Create a job

```php
// Dependency inject the worker or fetch it from the container
$fibonacciWorker = $container->get('App\Worker\FibonacciWorker');

// For Symfony 3.3, 3.4
//     $fibonacciWorker = $container->get('AppBundle\Worker\FibonacciWorker');
//

// For Symfony 2, 3.0, 3.1, 3.2:
//     $fibonacciWorker = $container->get('app.worker.fibonacci');


// Basic Examples
$fibonacciWorker->later()->fibonacci(20);
$fibonacciWorker->later()->fibonacciFile(20);

// Batch Example
$fibonacciWorker->batchLater()->fibonacci(20); // Batch up runs into a single run

// Timed Example
$fibonacciWorker->later(90)->fibonacci(20); // Run 90 seconds later

// Priority
//    Note: whether 1 == High or Low priority is configurable, but by default it is High
$fibonacciWorker->later(0, 1); // As soon as possible, High priority
$fibonacciWorker->later(0, 125); // Medium priority
$fibonacciWorker->later(0, 255); // Low priority

// Advanced Usage Example:
//  (If the job is not processed by $expireTime, then don't execute it ever...)
$expireTime = time() + 3600;
$fibonacciWorker->later()->setExpiresAt(new \DateTime("@$expireTime"))->fibonacci(20); // Must be run within the hour or not at all
```

##### Create Jobs - Additional Information

For further instructions on creating jobs, including how to *create a job from the __command line__*, see:

[/Resources/doc/create-job.md](/Resources/doc/create-job.md)

Running Jobs
------------
It's recommended that you background the following console commands

```bash
bin/console dtc:queue:run -d 120
```

```bash
# the -d parameter is the number of seconds to run
#  For example you could put the above command into cron or a cron-like system to run every 2 minutes
#
# There are a number of other parameters that could be passed to dtc:queue:run run this for a full list:
bin/console dtc:queue:run --help
```

Pruning Jobs
------------
For ODM and ORM based stores, the archive tables and the regular job queues can require periodic pruning.

For Mongo in production, it may be prudent to use a [capped collection](https://docs.mongodb.com/manual/core/capped-collections/) or [TTL Indexes](https://docs.mongodb.com/manual/core/index-ttl/)

For Mysql you could create an event to delete data periodically.

Nevertheless there are also several commands that exist that do similarly (and could be put into a periodic cron job as well)

```bash
bin/console dtc:queue:prune old --older 1m
# (deletes jobs older than one month from the Archive table)

# May be needed if jobs stall out:
bin/console dtc:queue:prune stalled

# If you're recording runs...this is recommended:
bin/console dtc:queue:prune stalled_runs

# If you're recording runs...another recommendation
bin/console dtc:queue:prune old_runs --older 1m

# If you're recording timings
bin/console dtc:queue:prune old_job_timings --older 1m

# You can tune 1m to a smaller interval such as 10d (10 days) or even 1800s (1/2 hour)
#  if you have too many jobs flowing through the system.
```

```bash
bin/console dtc:queue:prune --help # lists other prune commands
```

Debugging
---------
These commands may help with debugging issues with the queue:

```bash
bin/console dtc:queue:count # some status about the queue if available (ODM/ORM only)
bin/console dtc:queue:reset # resets errored and/or stalled jobs

# This is really only good for
bin/console dtc:queue:run --id={jobId}

# (jobId could be obtained from mongodb / or your database, if using an ORM / ODM solution)
```

Tracking Runs
-------------
Each runs can be tracked in a table in an ORM / ODM backed datastore.

Ways to configure:
__app/config/config.yml:__ (symfony 2/3)
__config/packages/dtc_queue.yaml:__ (symfony 4)
```yaml
dtc_queue:
    manager:
        # run defaults to whatever job is set to (which defaults to "odm", i.e. mongodb)
        #   If you set the job to rabbit_mq, or beanstalkd or something else, you need to set run
        #   to an ORM / ODM run_manager (or a custom such one) in order to get the runs to save
        #
        run: orm # other possible option is "odm" (i.e. mongodb)
    #
    # (optionally define your own run manager with id: dtc_queue.manager.run.{some_name} and put {some_name} under run:
```

MongoDB DocumentManager
------------------------
Change the document manager

__app/config/config.yml:__ (symfony 2/3)
__config/packages/dtc_queue.yaml:__ (symfony 4)
```yaml
dtc_queue:
    odm:
        document_manager: {something} # default is "default"
```

Mysql / ORM Setup
-----------------

### As of 4.0, ORM requires the [bcmath](http://php.net/manual/en/book.bc.php) extension to be enabled

__app/config/config.yml:__ (symfony 2/3)
__config/packages/dtc_queue.yaml:__ (symfony 4)
```yaml
dtc_queue:
    manager:
       job: orm
```

__Change the EntityManager:__

```yaml
dtc_queue:
    orm:
        entity_manager: {something} # default is "default"
```

__NOTE:__ You may need to add DtcQueueBundle to your mappings section in config.yml if auto_mapping is not enabled

```yaml
doctrine:
   #...
   orm:
       #...
       mappings:
           DtcQueueBundle: ~
```

Note on NON-ORM Setups:
-----------------------
If you plan on using ODM or Redis or another configuration, but you have Doctrine ORM enabled elsewhere, it's recommended that you use the *[schema_filter](https://symfony.com/doc/master/bundles/DoctrineMigrationsBundle/index.html#manual-tables)* configuration parameter so that schema dumps and/or migration diffs don't pickup those tables (see [issue #77](https://github.com/mmucklo/DtcQueueBundle/issues/77)).

E.g.
```yaml
doctrine:
   # ...
   dbal:
       # ...
       schema_filter: ~^(?!dtc_)~
```

_(if you already have a schema_filter, you can just add the "dtc\_" prefix to it.)_

Beanstalk Configuration
------------------------

__app/config/config.yml:__ (symfony 2/3)
__config/packages/dtc_queue.yaml:__ (symfony 4)
```yaml
dtc_queue:
    beanstalkd:
        host: beanstalkd
        tube: some-tube-name [optional]
    manager:
        job: beanstalkd
```

RabbitMQ Configuration
----------------------

__app/config/config.yml:__ (symfony 2/3)
__config/packages/dtc_queue.yaml:__ (symfony 4)
```yaml
dtc_queue:
    manager:
        job: rabbit_mq
    rabbit_mq:
        host: rabbitmq
        port: 5672
        user: guest
        password: guest
        vhost: "/" [optional defaults to "/"]
        ssl: [optional defaults to false - toggles to use AMQPSSLConnection]
        options: [optional options to pass to AMQPStreamConnection or AMQPSSLConnection]
        ssl_options: [optional extra ssl options to pass to AMQPSSLConnection]
        queue_args: [optional]
            queue: [optional queue name]
            passive: [optional defaults to false]
            durable: [optional defaults to true]
            exlusive: [optional defaults to false]
            auto_delete: [optional defaults to false]
        exchange_args: [optional]
            exchange: [optional queue name]
            type: [optional defaults to "direct"]
            passive: [optional defaults to false]
            durable: [optional defaults to true]
            auto_delete: [optional defaults to false]
```

Redis Configuration
-------------------

__app/config/config.yml:__ (symfony 2/3)
__config/packages/dtc_queue.yaml:__ (symfony 4)
```yaml
dtc_queue:
    manager:
        job: redis
    redis:
        # choose one of the below snc_redis, predis, or phpredis
        snc_redis:
           type: predis
           alias: default
        predis:
            # choose one of dns or connection_parameters
            dsn: redis://localhost
            connection_parameters:
                scheme: tcp
                host: localhost
                port: 6379
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
            host: localhost
            port: 6379
            timeout: 0
            retry_interval: ~
            read_timeout: 0
            auth: ~
```

Custom Jobs and Managers
------------------------

__app/config/config.yml:__ (symfony 2/3)
__config/packages/dtc_queue.yaml:__ (symfony 4)
```yaml
dtc_queue:
    class_job: Some\Job\ClassName [optional]
    manager:
        job: some_name [optional]
    # (create your own manager service and name or alias it:
    #   dtc_queue.manager.job.<some_name> and put
    #   <some_name> in the manager: job field above)
```

Rename the Database or Table Name
---------------------------------

1) Extend the following:

```
Dtc\QueueBundle\Document\Job
Dtc\QueueBundle\Document\JobArchive
```

   __or__

```
Dtc\QueueBundle\Entity\Job
Dtc\QueueBundle\Entity\JobArchive
```

(Depending on whether you're using MongoDB or an ORM)

2) Change the parameters on the class appropriately

```php
<?php
namespace App\Entity; // Or whatever

use Dtc\QueueBundle\Entity\Job as BaseJob;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="job_some_other_name", indexes={@ORM\Index(name="job_crc_hash_idx", columns={"crcHash","status"}),
 *                  @ORM\Index(name="job_priority_idx", columns={"priority","whenAt"}),
 *                  @ORM\Index(name="job_when_idx", columns={"whenAt"}),
 *                  @ORM\Index(name="job_status_idx", columns={"status","whenAt"})})
 */
class Job extends BaseJob {
}

// ... similarly for Entity\JobArchive if necessary
```

```php
<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Dtc\QueueBundle\Document\Job as BaseJob;

/**
 * @ODM\Document(db="my_db", collection="my_job_collection")
 */
class Job extends BaseJob
{
}

// ... similarly for Document\JobArchive if necessary
```


3) Add the new class(es) to config.yml

```yaml
# config.yml
# ...
dtc_queue:
    class_job: App\Entity\Job
    class_job_archive: App\Entity\JobArchive
```


Job Event Subscriber
--------------------

It's useful to listen to event in a long running script to clear doctrine manager or send email about status of a job. To
add a job event subscriber, create a new service with tag: dtc_queue.event_subscriber:

```yaml
services:
    voices.queue.listener.clear_manager:
        class: ClearManagerSubscriber
        arguments:
            - '@service_container'
        tags:
            - { name: dtc_queue.event_subscriber, connection: default }
```

ClearManagerSubscriber.php

```php
<?php
use Dtc\QueueBundle\EventDispatcher\Event;
use Dtc\QueueBundle\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClearManagerSubscriber
    implements EventSubscriberInterface
{
    private $container;
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function onPostJob(Event $event)
    {
        $managerIds = [
            'doctrine.odm.mongodb.document_manager',
            'doctrine.orm.default_entity_manager',
            'doctrine.orm.content_entity_manager'
        ];

        foreach ($managerIds as $id) {
            $manager = $this->container->get($id);
            $manager->clear();
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            Event::POST_JOB => 'onPostJob',
        );
    }
}
```

Running as upstart service:
---------------------------

1. Create the following file in /etc/init/. PHP is terrible at memory management
 and garbage collection: to deal with out of memory issues, run 20 jobs at
 a time. (Or a manageable job size)

```bash
# /etc/init/queue.conf

author "David Tee"
description "Queue worker service, run 20 jobs at a time, process timeout of 3600"

respawn
start on startup

script
        /{path to}/console dtc:queue:run --max_count 20 -v -t 3600>> /var/logs/queue.log 2>&1
end script
```

2. Reload config: sudo initctl reload-configuration
3. Start the script: sudo start queue

Admin
-----

You can register admin routes to see queue status. In your routing.yml file, add the following:

```yaml
dtc_queue:
    resource: '@DtcQueueBundle/Resources/config/routing.yml'
```

Testing
-------

You can run unittest by typing `bin/phpunit` in source folder. If you want to run
integration testing with Mongodb, you need to set up Mongodb server on
localhost and run:

```bash
bin/phpunit Tests/Document/JobManagerTest.php
```

If you want to run Beanstalkd integration testing, you need to run a local, empty instance of beanstalkd for testing.

```bash
sudo service beanstalkd restart; BEANSTALD_HOST=localhost bin/phpunit Tests/BeanStalkd/JobManagerTest.php
```

Full Configuration
==================
See [/Resources/doc/full-configuration.md](/Resources/doc/full-configuration.md)

License
-------

This bundle is under the MIT license (see LICENSE file under [Resources/meta/LICENSE](Resources/meta/LICENSE)).

Credit
--------
Originally written by @dtee
Enhanced and maintained by @mmucklo

