### 28.6.7-20261110

* Kept the Manticore Search engine on pinned `dev-28.6.7` (`e8149e71c`)
* Restored opt-in master-slave quorum recovery from worker 0
* Made quorum recovery fail closed when any live peer is primary or unreachable
* Made automatic table enrollment tolerate stale/concurrent cluster status
* Kept non-ready or incomplete workers out of balancer agent lists
* Added required-table readiness checks for safe replica scale-out
* Added balancer PodDisruptionBudget and PrometheusRule chart resources
* Added configurable StatefulSet update strategy for controlled upgrades

### 29.9.1-20261110

* Synced the SweetTV fork with upstream Manticore Helm 29.9.1
* Published SweetTV wrapper images from GHCR
* Kept worker `server_id` values one-based for stable cluster identity
* Allowed balancer probes to override the default TCP handler

### 29.9.0.1

* Fixed worker and balancer images to use Manticore Search 29.9.0.

### 29.9.0

* Switched to ManticoreSearch version 29.9.0
* feat: add worker mlock IPC_LOCK support
