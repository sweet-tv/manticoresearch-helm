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
