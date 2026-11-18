<?php

declare(strict_types=1);

use Capell\Referer\Actions\RecordRefererCountAction;
use Illuminate\Database\Connection;

/**
 * Drives the per-database lock-timeout strategy against a recording connection so the
 * MySQL, MariaDB and PostgreSQL write paths are exercised without those servers.
 */
function refererTimeoutRun(string $method, Connection $connection, int $milliseconds): Closure
{
    $closure = (new ReflectionMethod(RecordRefererCountAction::class, $method))
        ->invoke(resolve(RecordRefererCountAction::class), $connection, $milliseconds);

    if (! $closure instanceof Closure) {
        throw new RuntimeException('Expected a restore closure.');
    }

    return $closure;
}

/** @return array{0: Connection, 1: ArrayObject<int, string>} */
function refererRecordingConnection(?object $selectOne, string $serverVersion = '8.0.36'): array
{
    /** @var ArrayObject<int, string> $statements */
    $statements = new ArrayObject;
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('statement')->andReturnUsing(static function (string $sql) use ($statements): bool {
        $statements->append($sql);

        return true;
    });
    $connection->shouldReceive('selectOne')->andReturnUsing(static fn (string $sql): ?object => str_contains($sql, 'max_statement_time')
        ? (object) ['timeout' => '0.000']
        : $selectOne);
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('getAttribute')->with(PDO::ATTR_SERVER_VERSION)->andReturn($serverVersion);
    $connection->shouldReceive('getPdo')->andReturn($pdo);

    return [$connection, $statements];
}

it('applies and restores PostgreSQL local timeouts', function (): void {
    [$connection, $statements] = refererRecordingConnection(null);

    $restore = refererTimeoutRun('applyPostgresTimeout', $connection, 750);
    $restore();

    expect($statements->getArrayCopy())->toBe([
        "SET LOCAL lock_timeout = '750ms'",
        "SET LOCAL statement_timeout = '750ms'",
    ]);
});

it('applies and restores SQLite busy timeouts', function (): void {
    [$connection, $statements] = refererRecordingConnection((object) ['timeout' => 4000]);

    $restore = refererTimeoutRun('applySqliteTimeout', $connection, 900);
    $restore();

    expect($statements->getArrayCopy())->toBe([
        'PRAGMA busy_timeout = 900',
        'PRAGMA busy_timeout = 4000',
    ]);
});

it('applies MySQL session timeouts in whole seconds and restores the previous values', function (): void {
    [$connection, $statements] = refererRecordingConnection((object) ['innodb_timeout' => '50', 'metadata_timeout' => '31536000']);

    $restore = refererTimeoutRun('applyMySqlTimeout', $connection, 1500);
    $restore();

    expect($statements->getArrayCopy())->toBe([
        'SET SESSION innodb_lock_wait_timeout = 2',
        'SET SESSION lock_wait_timeout = 2',
        'SET SESSION innodb_lock_wait_timeout = 50',
        'SET SESSION lock_wait_timeout = 31536000',
    ]);
});

it('also bounds the statement time on MariaDB and restores it', function (): void {
    [$connection, $statements] = refererRecordingConnection((object) ['innodb_timeout' => '50', 'metadata_timeout' => '60'], '10.11.6-MariaDB');

    $restore = refererTimeoutRun('applyMySqlTimeout', $connection, 500);
    $restore();

    expect($statements->getArrayCopy())->toContain('SET SESSION max_statement_time = 0.500')
        ->and($statements->getArrayCopy())->toContain('SET SESSION max_statement_time = 0.000');
});

it('refuses to write when the current MySQL timeouts cannot be read', function (): void {
    [$connection, $statements] = refererRecordingConnection(null);

    expect(fn (): Closure => refererTimeoutRun('applyMySqlTimeout', $connection, 500))->toThrow(RuntimeException::class)
        ->and($statements->getArrayCopy())->toBe([]);
});
