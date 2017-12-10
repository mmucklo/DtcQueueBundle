# Symfony 2 / 3 Installation Instructions

Then add the bundle to __AppKernel.php__:

```php
<?php

//...

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
                    //...
                    new \Dtc\GridBundle\DtcGridBundle(),
                    new \Dtc\QueueBundle\DtcQueueBundle(),
// ...
```

MongoDB Setup:
  * Add MongoDB ODM setting for Job Document.

__app/config/config.yml:__
```yaml
doctrine_mongodb:
    document_managers:
        default:
            mappings:
                DtcQueueBundle:
                    dir: Document/
                    type: annotation
```
ORM Setup:

__app/config/config.yml:__
```yaml
dtc_queue:
    default_manager: orm
```

__NOTE:__ You may need to add DtcQueueBundle to your mappings section in config.yml if auto_mapping is not enabled

__app/config/config.yml:__
```yaml
doctrine:
   #...
   orm:
       #...
       mappings:
           DtcQueueBundle: ~
```

   * You'll need to create the schemas in your database by using one of:
      * bin/console doctrine:schema:update --dump-sql
      * bin/console doctrine:schema:update --force
      * Doctrine Migrations (requires [DoctrineMigrationsBundle](https://github.com/doctrine/DoctrineMigrationsBundle) to be installed):
         * bin/console doctrine:migrations:diff --filter-expression=/dtc_/
         * then:
            * bin/console doctrine:migrations:migrate

Additionally, if you choose to record job timings (to display in the /timings display), you'll need to install and configure doctrine extensions that support 
 the YEAR, MONTH, DAY, HOUR functions:

   * Bundle #1 [beberlei/doctrineextensions][https://github.com/beberlei/DoctrineExtensions]
      * Then add the Date and Time functions as instructed here:
         * [mysql](https://github.com/beberlei/DoctrineExtensions/blob/master/config/mysql.yml)
   * Bundle #2 [oroinc/doctrine-extensions](https://github.com/oroinc/doctrine-extensions)
      * Then add the Date and Time functions as instructed here:
         * [symfony2](https://github.com/oroinc/doctrine-extensions#symfony2)
            * (should work for symfony 3 as well, though labeled symfony2)
         
#### Optional Admin

Add this to your app/config/routing.yml file:

```yaml
dtc_queue:
    resource: '@DtcQueueBundle/Resources/config/routing.yml'
dtc_grid:
    resource: '@DtcGridBundle/Resources/config/routing.yml'
```

__Routes:__
   * /dtc_queue/jobs
      * ODM / ORM only
   * /dtc_queue/all_jobs
      * ODM / ORM list of all jobs
   * /dtc_queue/runs
      * ODM / ORM only (or another type of queue with an ODM / ORM run_manager)
   * /dtc_queue/status
   * /dtc_queue/trends
      * Graph (requires job_timings: true and an ODM / ORM run_manager)

#### Securing the Admin section

In app/config/security.yml do the following:

__app/config/security.yml:__
```yaml
security:
    # ...
    providers:
        # ...
    firewall:
        # ...
    access_control:
        # ...
        - { path: ^/dtc_queue, roles: ROLE_ADMIN }
        - { path: ^/dtc_grid, roles: ROLE_ADMIN }
```
