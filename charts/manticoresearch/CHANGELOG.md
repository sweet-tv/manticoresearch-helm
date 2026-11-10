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
