<?php

use Core\Manticore\ManticoreConnector;
use Monolog\Handler\NullHandler;
use Core\Logger\Logger;
use SweetTv\Manticore\ClusterTableSynchronizer;

require '/etc/manticoresearch/vendor/autoload.php';
require '/etc/manticoresearch/cluster_tables.php';

Logger::setHandler(new NullHandler());

final class FakeManticoreConnector extends ManticoreConnector
{
    public array $attempts = [];
    public array $added = [];
    public array $clusterTables = [];
    public array $racingTables = [];
    public array $failingTables = [];
    private array $tables;

    public function __construct(array $tables, array $clusterTables = [])
    {
        $this->tables = $tables;
        $this->clusterTables = $clusterTables;
    }

    public function setMaxAttempts($maxAttempts): void
    {
        $this->attempts[] = $maxAttempts;
    }

    public function getTables($log = true, $typeFilter = null): array
    {
        return $this->tables;
    }

    public function getStatus($log = true): void
    {
    }

    public function getNotInClusterTables($tables = null): array
    {
        return array_values(array_diff($tables ?? $this->tables, $this->clusterTables));
    }

    public function addTableToCluster($tableName, $log = true): bool
    {
        $this->added[] = $tableName;

        if (in_array($tableName, $this->racingTables, true)) {
            $this->clusterTables[] = $tableName;
            throw new RuntimeException('already in cluster');
        }

        if (in_array($tableName, $this->failingTables, true)) {
            throw new RuntimeException('genuine failure');
        }

        $this->clusterTables[] = $tableName;

        return true;
    }
}

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message.' expected='.json_encode($expected).' actual='.json_encode($actual).PHP_EOL);
        exit(1);
    }
}

$connector = new FakeManticoreConnector(
    ['existing', 'racing', 'missing'],
    ['existing']
);
$connector->racingTables = ['racing'];

(new ClusterTableSynchronizer($connector))->synchronize();

assertSameValue(['racing', 'missing'], $connector->added, 'unexpected add sequence');
assertSameValue(['existing', 'racing', 'missing'], $connector->clusterTables, 'tables were not synchronized');
assertSameValue([0, 180, 0, 180], $connector->attempts, 'ALTER retry limits were not scoped per mutation');

$failingConnector = new FakeManticoreConnector(['broken']);
$failingConnector->failingTables = ['broken'];

try {
    (new ClusterTableSynchronizer($failingConnector))->synchronize();
    fwrite(STDERR, "genuine failure was ignored\n");
    exit(1);
} catch (RuntimeException $exception) {
    assertSameValue('genuine failure', $exception->getMessage(), 'wrong failure propagated');
    assertSameValue([0, 180], $failingConnector->attempts, 'retry limits were not restored after failure');
}

echo "cluster table synchronizer tests passed\n";
