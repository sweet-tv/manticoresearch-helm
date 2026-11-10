#!/bin/sh

set -eu

if [ "${QUORUM_RECOVERY:-false}" != "true" ]; then
  exec sleep infinity
fi

while true; do
  php /etc/manticoresearch/quorum.php || true
  sleep "${QUORUM_RUN_INTERVAL:-15}"
done
