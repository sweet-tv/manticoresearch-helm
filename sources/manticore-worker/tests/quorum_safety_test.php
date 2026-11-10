<?php

use Core\Logger\Logger;
use Monolog\Handler\NullHandler;
use SweetTv\Manticore\QuorumSafetyChecker;

require '/etc/manticoresearch/vendor/autoload.php';
require '/etc/manticoresearch/quorum_safety.php';

Logger::setHandler(new NullHandler());

final class FakeQuorumPeer
{
    public function __construct(private bool $primary)
    {
    }

    public function isClusterPrimary(): bool
    {
        return $this->primary;
    }
}

function assertBoolean(bool $expected, bool $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message.PHP_EOL);
        exit(1);
    }
}

$checker = new QuorumSafetyChecker();
$podIps = [
    'manticore-worker-0' => '10.0.0.10',
    'manticore-worker-1' => '10.0.0.11',
];

assertBoolean(
    true,
    $checker->allReachablePeersNonPrimary(
        $podIps,
        'manticore-worker-0',
        static fn (string $ip): FakeQuorumPeer => new FakeQuorumPeer(false)
    ),
    'non-primary peer should allow bootstrap'
);

assertBoolean(
    false,
    $checker->allReachablePeersNonPrimary(
        $podIps,
        'manticore-worker-0',
        static fn (string $ip): FakeQuorumPeer => new FakeQuorumPeer(true)
    ),
    'primary peer should block bootstrap'
);

assertBoolean(
    false,
    $checker->allReachablePeersNonPrimary(
        $podIps,
        'manticore-worker-0',
        static function (string $ip): never {
            throw new RuntimeException('unreachable');
        }
    ),
    'unreachable peer should fail closed'
);

$factoryCalledForSelf = false;
assertBoolean(
    true,
    $checker->allReachablePeersNonPrimary(
        ['manticore-worker-0' => '10.0.0.10'],
        'manticore-worker-0',
        static function (string $ip) use (&$factoryCalledForSelf): FakeQuorumPeer {
            $factoryCalledForSelf = true;
            return new FakeQuorumPeer(true);
        }
    ),
    'self must not block bootstrap'
);
assertBoolean(false, $factoryCalledForSelf, 'self peer should not be queried');

echo "quorum safety tests passed\n";
