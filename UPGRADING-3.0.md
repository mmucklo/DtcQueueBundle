# 3.0
  * The JobTiming entity has an additional integer column "status" (ORM JobTiming tables will need a migration/update)
     * it also has that column added to the index (ORM based JobTiming tables will need a migration/update for the index as well)
  * dtc:queue:run
     * --nano_sleep is now --nano-sleep
     * --max_count is now --max-count
