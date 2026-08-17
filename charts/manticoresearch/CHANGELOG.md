### 28.6.7-20260817

* Added optional persistent balancer state for deployments that manage runtime distributed-table aliases
* Use a single-replica recreate strategy when balancer persistence is enabled to prevent concurrent access to the same data volume
* Reconcile worker tables through SQL in persistent mode so runtime tables and the observer can coexist without a startup race

### 28.6.7-20260804

* Switched to ManticoreSearch development version dev-28.6.7
* Published SweetTV fork images from GHCR instead of Docker Hub
* Generate worker `server_id` values from 1-based StatefulSet ordinals for SweetTV worker identity stability

### 28.4.4-20260720

* Generate worker `server_id` values from 1-based StatefulSet ordinals to avoid conflicts while scaling beyond two workers
* Configure balancers from Kubernetes Ready workers only, preventing not-yet-synced joining pods from being added to distributed table agents
* Published fork images from GHCR instead of Docker Hub

### 28.4.4

* Switched to ManticoreSearch 28.4.4

### 25.0.0-20260612

* Added persistent balancer agent support via `balancer.config.agent.conn=pconn`
* Published fork images from GHCR instead of Docker Hub

### 25.0.0-20260611

* Switched to ManticoreSearch 25.0.0
* Exposed binary protocols to worker and balancer services
* Add binary port listener to balancer
* Enhanced probes for improved support of large volumes
* Introduced config maps and additional volumes to better support wordforms
* Added searchd start flags support
