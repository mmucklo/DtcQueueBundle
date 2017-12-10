# 3.0
  * JobTiming has an additional integer column "status" (ORM JobTiming tables will need a migration)
     * it also has that column added to the index (ORM based JobTiming tables will need a migration)
  * dtc:queue:run
     * --nano_sleep is now --nano-sleep
     * --max_count is now --max-count
  