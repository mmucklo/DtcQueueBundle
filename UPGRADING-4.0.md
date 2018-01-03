# 4.0

* Configuration changes:

If you set various classes in the configuration

3.0 (old)
```yaml
dtc_queue:
   # ...
   class_job: ...
   class_job_archive: ...
   priority_max: ...
   priority_direction: ...
```

4.0 (new)
```yaml
dtc_queue:
   # ...
   class:
       job: ...
       job_archive: ...
       # etc.
   priority:
       max: ...
       direction: ...
```

* ORM - Job tables will need a migration or table update as several fields have been renamed
   * **RENAMES**
      * error_count -> exceptions
      * stalled_count -> stalls
      * max_stalled -> max_stalls
      * max_errors -> max_exceptions   
   * **NEW**
      * failures
      * max_failures
* Worker updates
   * **Important**: Remove all $this->jobClass = // some class
   * Replace any $this->jobClass or $this->getJobClass() function calls with:
      * $this->getJobManager()->getJobClass()
   * Replace any $this->jobManager direct access calls with:
      * $this->getJobManager()
* Refactoring
   * If you extended JobManager, please note the following
      * Base classes have been moved to their own "Manager" folder
      * resetErroneousJobs -> resetExceptionJobs
   * If you extended any of the Job classes in Model, please note the following
      * locked and lockedAt have been moved to DoctrineJob
      * stallCount and maxStalled are now in StallableJob (used to be stalledCount / maxStalled)
      * errorCount and maxErrors are renamed to exceptions and maxExceptions and are now in RetryableJob