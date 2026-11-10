<?php

use Core\K8s\ApiClient;
use Core\K8s\Resources;
use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use Core\Notifications\NotificationStub;
use Monolog\Handler\StreamHandler;
use SweetTv\Manticore\QuorumSafetyChecker;

require 'vendor/autoload.php';
require 'quorum_safety.php';

const REPLICATION_MODE_MASTER_SLAVE = 'master-slave';

$qlPort = null;
$binaryPort = null;
$clusterName = null;
$instance = null;
$workerService = null;
$autoAddTablesIntoCluster = null;
$replicationMode = null;
$labels = null;
$logLevel = null;
$isBalancerEnabled = true;
$quorumRecovery = false;
$quorumCheckInterval = 15;
include 'env_reader.php';

Logger::setHandler(new StreamHandler('php://stdout', $logLevel));

if (!$quorumRecovery || $replicationMode !== REPLICATION_MODE_MASTER_SLAVE) {
    exit(0);
}

try {
    $resources = new Resources(new ApiClient(), (array)$labels, new NotificationStub());

    // In master-slave mode worker 0 is the authoritative recovery node.
    if (gethostname() !== $resources->getMinReplicaName()) {
        exit(0);
    }

    $manticore = new ManticoreConnector('localhost', $qlPort, $clusterName, 0);
    if (!$manticore->checkClusterName() || $manticore->isClusterPrimary()) {
        exit(0);
    }

    $safetyChecker = new QuorumSafetyChecker();
    $podIps = $resources->getPodIpAllConditions();
    if (!$safetyChecker->allReachablePeersNonPrimary(
        $podIps,
        gethostname(),
        static fn (string $ip): ManticoreConnector => new ManticoreConnector($ip, $qlPort, $clusterName, 0)
    )) {
        exit(0);
    }

    Logger::warning('Cluster has no primary component; bootstrapping quorum from worker 0');
    if (!$manticore->restoreCluster()) {
        throw new RuntimeException('Manticore rejected quorum recovery');
    }
    Logger::info('Quorum recovery completed');
} catch (Throwable $exception) {
    Logger::error('Quorum recovery check failed: '.$exception->getMessage());
}
