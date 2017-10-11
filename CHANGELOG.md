### 2.0
   * New ORM storage
   * 
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
   * renamed when to whenAt
   * renamed expires to expiresAt
   * Added new ORM JobManager
   * RunCommand:
       * Added new options:
           * 
           
       * Renamed command namespace:
           * Old: dtc:queue_worker:*
           * New: dtc:queue:*
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