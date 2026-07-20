<?php


use Core\Cache\Cache;
use Core\K8s\ApiClient;
use Core\Logger\Logger;
use Core\Manticore\ManticoreConnector;
use Core\Mutex\Locker;

require 'vendor/autoload.php';


$workerPort      = null;
$balancerPort    = null;
$clusterName     = null;
$instance        = null;
$configMapPath   = null;
$workerService   = null;
$tableHAStrategy = null;
$agentConnection = null;

$variables = [
	'workerPort'      => [ 'env' => 'WORKER_PORT', 'type' => 'int' ],
	'balancerPort'    => [ 'env' => 'BALANCER_PORT', 'type' => 'int' ],
	'clusterName'     => [ 'env' => 'CLUSTER_NAME', 'type' => 'string' ],
	'instance'        => [ 'env' => 'INSTANCE_LABEL', 'type' => 'string' ],
	'configMapPath'   => [ 'env' => 'CONFIGMAP_PATH', 'type' => 'string' ],
	'workerService'   => [ 'env' => 'WORKER_SERVICE', 'type' => 'string' ],
	'tableHAStrategy' => [ 'env' => 'TABLE_HA_STRATEGY', 'type' => 'string' ],
	'agentConnection' => [ 'env' => 'AGENT_CONNECTION', 'type' => 'string' ],
];

foreach ( $variables as $variable => $desc ) {
	$$variable = getenv( $desc['env'] );

	if ( $$variable === false ) {
		Logger::info( $desc['env'] . " is not defined\n" );
		exit( 1 );
	}

	if ( $desc['type'] === 'int' ) {
		$$variable = (int) $$variable;
	} elseif ( $desc['type'] === 'bool' ) {
		$$variable = (bool) $$variable;
	}
}

if (!in_array($tableHAStrategy, ['random', 'nodeads','noerrors','roundrobin'])){
	Logger::info( "TABLE_HA_STRATEGY can be only {random|nodeads|noerrors|roundrobin}\n" );
	exit( 1 );
}

$agentConnection = strtolower(trim($agentConnection));
if (!in_array($agentConnection, ['', 'pconn'], true)){
	Logger::info( "AGENT_CONNECTION can be empty or pconn\n" );
	exit( 1 );
}

$labels = [
	'app.kubernetes.io/component' => 'worker',
	'app.kubernetes.io/instance'  => $instance,
];

if ( ! file_exists( $configMapPath ) ) {
	throw new RuntimeException( "Searchd config is not mounted" );
}

$cache  = new Cache();
$locker = new Locker( 'observer' );
$locker->checkLock();

$api = new ApiClient();

$workerPods = getReadyWorkerPods( $api, $labels );
if ( $workerPods === [] ) {
	Logger::info( "No ready workers found" );
	$locker->unlock();
}
$oldestWorker = getOldestPodName( $workerPods );

if ( empty( $oldestWorker ) ) {
	throw new RuntimeException( "Can't find oldest ready worker" );
}

$manticore = new ManticoreConnector( $oldestWorker . '.' . $workerService, $workerPort, null, - 1 );
$tables    = $manticore->getTables( false );
$podsIps   = getPodIps( $workerPods );

sort( $tables );
sort( $podsIps, SORT_NATURAL );


if ( $tables !== [] ) {
	$previousHash = $cache->get( Cache::TABLE_HASH );
	$hash         = sha1( implode( '.', $tables ) . implode( '.', $podsIps ) . $agentConnection );

	if ( $previousHash !== $hash ) {
		Logger::info( "Starting config recompiling" );
		saveConfig( $tables, $podsIps, $balancerPort, $configMapPath, $tableHAStrategy, $agentConnection );
		$cache->store( Cache::TABLE_HASH, $hash );
	}
} else {
	Logger::info( "No tables found" );
	$locker->unlock();
}


function buildDistributedTableAgentValue( $table, $nodes, $agentConnection ) {
	$agent = implode( "|", $nodes );
	$options = [];

	if ( $agentConnection === 'pconn' ) {
		$options[] = 'conn=pconn';
	}

	if ( $options !== [] ) {
		$agent .= ":" . $table . "[" . implode( ",", $options ) . "]";
	}

	return $agent;
}


function getReadyWorkerPods( ApiClient $api, array $labels ): array {
	$pods = $api->getManticorePods( $labels );
	if ( ! isset( $pods['items'] ) || ! is_array( $pods['items'] ) ) {
		return [];
	}

	$readyPods = [];
	foreach ( $pods['items'] as $pod ) {
		if ( ( $pod['status']['phase'] ?? null ) !== 'Running' ) {
			continue;
		}
		if ( empty( $pod['status']['podIP'] ) ) {
			continue;
		}
		if ( ! isPodReady( $pod ) ) {
			continue;
		}

		$readyPods[] = $pod;
	}

	usort(
		$readyPods,
		static function ( array $left, array $right ): int {
			$leftCreated  = $left['metadata']['creationTimestamp'] ?? '';
			$rightCreated = $right['metadata']['creationTimestamp'] ?? '';
			if ( $leftCreated === $rightCreated ) {
				return strcmp( $left['metadata']['name'] ?? '', $right['metadata']['name'] ?? '' );
			}

			return strcmp( $leftCreated, $rightCreated );
		}
	);

	return $readyPods;
}


function isPodReady( array $pod ): bool {
	foreach ( $pod['status']['conditions'] ?? [] as $condition ) {
		if ( ( $condition['type'] ?? null ) === 'Ready' && ( $condition['status'] ?? null ) === 'True' ) {
			return true;
		}
	}

	return false;
}


function getOldestPodName( array $pods ): ?string {
	return $pods[0]['metadata']['name'] ?? null;
}


function getPodIps( array $pods ): array {
	return array_values(
		array_map(
			static fn( array $pod ): string => $pod['status']['podIP'],
			$pods
		)
	);
}


function saveConfig( $tables, $nodes, $port, $configMapPath, $tableHAStrategy, $agentConnection ) {
	$searchdConfig = file_get_contents( $configMapPath );
	$prependConfig = '';
	foreach ( $tables as $table ) {
		$prependConfig .= "\n\nindex " . $table . "\n" .
		                  "{\n" .
		                  "\ttype = distributed\n" .
		                  "\tha_strategy = " . $tableHAStrategy . "\n" .
		                  "\tagent = " . buildDistributedTableAgentValue( $table, $nodes, $agentConnection ) . "\n" .
		                  "}\n\n";
	}

	file_put_contents(
		DIRECTORY_SEPARATOR . 'etc' .
		DIRECTORY_SEPARATOR . 'manticoresearch' .
		DIRECTORY_SEPARATOR . 'manticore.conf',
		$prependConfig . $searchdConfig
	);

	( new ManticoreConnector( 'localhost', $port, null, - 1 ) )->reloadTables();
}


$locker->unlock( 0 );
