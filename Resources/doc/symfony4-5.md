# Symfony 4/5 Installation Instructions

## Composer installation

First -

   * Turn on contrib recipie support
      * composer config extra.symfony.allow-contrib true

   * Then require the packages
      * composer require mmucklo/queue-bundle
   
### Post install instructions

   * If you want the admin pages, you'll need to install symfony templating
      * composer require symfony/templating
       
   * Symfony 4
      * Inside of config/packages/framework.yaml, make sure you have the twig templating engine turned on:
      * (this also requires the symfony/framework package if you haven't already installed it)

```yaml
framework:
    # ...
    # Symfony 4:
    templating:
        engines: ['twig']
```

#### MongoDB configuration

You'll need to update your __config/packages/doctrine_mongodb.yaml__ file

__config/packages/doctrine_mongodb.yaml:__
```yaml
doctrine_mongodb:
    # ...
    document_managers:
        default:
            # ...
            mappings:
                # ...
                DtcQueueBundle:
                    dir: Document/
                    type: annotation
```

#### ORM configuration

__config/packages/dtc_queue.yaml:__
```yaml
dtc_queue:
    manager:
        # This parameter is required and should typically be set to one of:
        #   [odm|orm|beanstalkd|rabbit_mq|redis]
        job: orm
```

See the full list of options in [/Resources/doc/full-configuration.md](/Resources/doc/full-configuration.md)

__NOTE:__ You may need to add DtcQueueBundle to your mappings section in __config/packages/doctrine.yaml__ if auto_mapping is not enabled

Finally,

   * You'll need to create the schemas in your database by using one of:
      * bin/console doctrine:schema:update --dump-sql
      * bin/console doctrine:schema:update --force
      * Doctrine Migrations (requires [DoctrineMigrationsBundle](https://github.com/doctrine/DoctrineMigrationsBundle) to be installed):
         * bin/console doctrine:migrations:diff --filter-expression=/dtc_/
         * then:
            * bin/console doctrine:migrations:migrate

Additionally, if you choose to record job timings (to display in the /timings display), you'll need to install and configure doctrine extensions that support 
 the YEAR, MONTH, DAY, HOUR functions:

   * Bundle #1 [beberlei/doctrineextensions](https://github.com/beberlei/DoctrineExtensions)
      * Then add the Date and Time functions as instructed here:
         * [mysql](https://github.com/beberlei/DoctrineExtensions/blob/master/config/mysql.yml)
   * Bundle #2 [oroinc/doctrine-extensions](https://github.com/oroinc/doctrine-extensions)
      * Then add the Date and Time functions as instructed here:
         * [symfony2](https://github.com/oroinc/doctrine-extensions#symfony2)
            * (should work for symfony 3 as well, though labeled symfony2)


#### Admin configuration

For the Admin pages, add this to your config/routes.yaml file:

__config/routes.yaml:__
```yaml
dtc_queue:
    resource: '@DtcQueueBundle/Resources/config/routing.yml'
dtc_grid:
    resource: '@DtcGridBundle/Resources/config/routing.yml'
```

__Routes:__
   * /dtc_queue/jobs
      * ODM / ORM only
   * /dtc_queue/jobs_all
      * ODM / ORM list of all jobs
   * /dtc_queue/runs
      * ODM / ORM only (or another type of queue with an ODM / ORM run_manager)
   * /dtc_queue/status
   * /dtc_queue/workers
   * /dtc_queue/trends
      * Graph (requires __job_timings: true__ and an ODM / ORM run_manager)

#### Securing the Admin section

If you haven't already:

```
composer require security
```

In config/packages/security.yaml do the following:

__config/packages/security.yaml:__
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
