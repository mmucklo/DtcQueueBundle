### 2.0

   * Config change: renamed mongo Documents to Document - you may need to update config.yml in the mongodb section:   
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
   * renamed when to whenAt (this may break any existing jobs that use when)
   * renamed expires to expiresAt
   * Added new ORM JobManager
   * RunCommand:
       * Added new options:
           * 
           
       * Renamed command namespace:
           * Old: dtc:queue_worker:*
           * New: dtc:queue:*
           