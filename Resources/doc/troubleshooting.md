#### Error
```
The "dtc_queue.document_manager" service is synthetic, it needs to be set at boot time before it can be used.
```

   * Symfony 4/5

      * Open up dtc_queue.yaml in config/packages/dtc_queue.yaml
      * Make sure the correct manager is selected.

   * Symfony 2/3
   
      * Make sure the correct manager is selected in app/config/config.

```yaml        
dtc_queue:
    # For full configuration options see:
    #   https://github.com/mmucklo/DtcQueueBundle/blob/master/Resources/doc/full-configuration.md
    manager:
        # This parameter is required and should typically be set to one of:
        #   [odm|orm|beanstalkd|rabbit_mq|redis]
        job: orm
```
        
#### Error
```        
An exception occurred while executing 'INSERT INTO dtc_queue_run (started_at, ended_at, elapsed, duration, last_heartbeat_at, max_coun
t, processed, hostname, pid, process_timeout, current_job_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' with params ["2020-03-25 15:37
:16", null, null, null, "2020-03-25 15:37:16", 1, 0, "5f86d19c1bc1", 2326, null, null]:

SQLSTATE[42S02]: Base table or view not found: 1146 Table 'symfony.dtc_queue_run' doesn't exist
```

The doctrine tables haven't been created. Recommend the following command to see which tables need to be created:

```bash
bin/console doctrine:schema:update --dry-run
```

If there are only dtc_queue tables to be created, you can try the following. Otherwise you may just want to grab the relevant dtc_queue statements from the **previous command** and apply them to the database manually
```bash
bin/console doctrine:schema:update --force
```

If you are using doctrine migrations, you could try generating a migration:
```bash
bin/console doctrine:migrations:diff --filter-expression='/dtc_queue_.+/'
```

Then apply the migration using one of:

```bash
bin/console doctrine:migrations:migrate

# or

bin/console doctirne:migrations:execute <migration-id>
```

#### Error

When visiting /dtc_queue/ in the browser, the following error is encountered:

```
The parameter "dtc_grid.theme.css" must be defined
```

Solution:

```
composer require mmucklo/grid-bundle
```
