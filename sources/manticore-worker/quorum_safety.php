<?php

namespace SweetTv\Manticore;

use Core\Logger\Logger;
use Throwable;

/**
 * Prevents quorum bootstrap while another live pod can still be primary.
 * Traffic readiness must not affect this decision: an incomplete but running
 * peer may be NotReady and still belong to a primary component.
 */
final class QuorumSafetyChecker
{
    public function allReachablePeersNonPrimary(
        array $podIps,
        string $selfHostname,
        callable $connectorFactory
    ): bool {
        foreach ($podIps as $hostname => $ip) {
            if ($hostname === $selfHostname) {
                continue;
            }

            try {
                $connector = $connectorFactory($ip);
                if ($connector->isClusterPrimary()) {
                    Logger::warning("Quorum recovery blocked: peer $hostname is primary");
                    return false;
                }
            } catch (Throwable $exception) {
                // A reachable Kubernetes pod that cannot be checked may be on
                // the other side of a network partition. Fail closed rather
                // than risk bootstrapping a second primary component.
                Logger::warning(
                    "Quorum recovery blocked: peer $hostname cannot be checked: ".$exception->getMessage()
                );
                return false;
            }
        }

        return true;
    }
}
