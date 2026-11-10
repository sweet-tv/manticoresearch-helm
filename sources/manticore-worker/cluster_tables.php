<?php

namespace SweetTv\Manticore;

use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use RuntimeException;

/**
 * Adds local tables to the replication cluster without treating a concurrent
 * or stale-status "already in cluster" response as a fatal startup error.
 */
final class ClusterTableSynchronizer
{
    private const STARTUP_QUERY_ATTEMPTS = 0;
    private const RUNTIME_QUERY_ATTEMPTS = 180;

    private ManticoreConnector $connector;

    public function __construct(ManticoreConnector $connector)
    {
        $this->connector = $connector;
    }

    public function synchronize(): void
    {
        foreach ($this->connector->getTables() as $table) {
            $this->connector->getStatus(false);
            if (!$this->isMissingFromCluster($table)) {
                continue;
            }

            try {
                // The upstream fetcher retries every SQL error. ALTER CLUSTER
                // can legitimately race with cluster-state propagation, so
                // bound only this mutation and decide from fresh status.
                $this->connector->setMaxAttempts(self::STARTUP_QUERY_ATTEMPTS);
                try {
                    $this->connector->addTableToCluster($table);
                } finally {
                    $this->connector->setMaxAttempts(self::RUNTIME_QUERY_ATTEMPTS);
                }
            } catch (RuntimeException $exception) {
                $this->connector->getStatus(false);
                if ($this->isMissingFromCluster($table)) {
                    throw $exception;
                }

                Logger::warning("Table $table is already present in the cluster; continuing startup");
            }
        }
    }

    private function isMissingFromCluster(string $table): bool
    {
        return $this->connector->getNotInClusterTables([$table]) !== [];
    }
}
