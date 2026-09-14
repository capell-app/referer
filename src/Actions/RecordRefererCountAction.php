<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\Core\Models\Site;
use Capell\Referer\Data\ReferralSourceData;
use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use PDO;
use RuntimeException;
use Throwable;

final class RecordRefererCountAction
{
    use AsObject;

    public function __construct(private readonly RefererHealthSignal $healthSignal) {}

    public function handle(Site $site, ReferralSourceData $source): bool
    {
        $date = now('UTC')->toDateString();

        try {
            DB::transaction(function () use ($site, $source, $date): void {
                $restoreTimeout = static function (): void {};

                try {
                    $restoreTimeout = $this->applyDatabaseTimeout();

                    DB::table('referer_daily_counts')->upsert(
                        [[
                            'site_id' => $site->getKey(),
                            'date' => $date,
                            'source_key' => $source->key,
                            'count' => 1,
                        ]],
                        ['site_id', 'date', 'source_key'],
                        ['count' => $this->incrementExpression('referer_daily_counts')],
                    );

                    DB::table('referer_source_totals')->upsert(
                        [[
                            'site_id' => $site->getKey(),
                            'source_key' => $source->key,
                            'count' => 1,
                            'collection_started_on' => $date,
                        ]],
                        ['site_id', 'source_key'],
                        ['count' => $this->incrementExpression('referer_source_totals')],
                    );
                } finally {
                    $restoreTimeout();
                }
            });
        } catch (Throwable) {
            $this->healthSignal->recordWriteFailure();

            return false;
        }

        return true;
    }

    private function incrementExpression(string $table): Expression
    {
        $connection = DB::connection();
        $expression = match ($connection->getDriverName()) {
            'pgsql' => match ($table) {
                'referer_daily_counts' => '"referer_daily_counts"."count" + 1',
                'referer_source_totals' => '"referer_source_totals"."count" + 1',
                default => throw new RuntimeException('The referral counter table is not supported.'),
            },
            'mysql', 'mariadb', 'sqlite' => 'count + 1',
            default => throw new RuntimeException('The referral counter driver is not supported.'),
        };

        return DB::raw($expression);
    }

    private function applyDatabaseTimeout(): Closure
    {
        $connection = DB::connection();
        $timeoutMilliseconds = min(
            5000,
            max(100, $this->positiveConfigInteger('capell-referer.write_timeout_ms', 1000)),
        );

        return match ($connection->getDriverName()) {
            'pgsql' => $this->applyPostgresTimeout($connection, $timeoutMilliseconds),
            'sqlite' => $this->applySqliteTimeout($connection, $timeoutMilliseconds),
            'mysql', 'mariadb' => $this->applyMySqlTimeout($connection, $timeoutMilliseconds),
            default => throw new RuntimeException('The configured database driver has no supported referral write timeout.'),
        };
    }

    private function applyPostgresTimeout(Connection $connection, int $timeoutMilliseconds): Closure
    {
        $timeout = $timeoutMilliseconds . 'ms';
        $connection->statement("SET LOCAL lock_timeout = '{$timeout}'");
        $connection->statement("SET LOCAL statement_timeout = '{$timeout}'");

        return static function (): void {};
    }

    private function applySqliteTimeout(Connection $connection, int $timeoutMilliseconds): Closure
    {
        $previous = $connection->selectOne('PRAGMA busy_timeout');
        $previousTimeout = is_object($previous) && isset($previous->timeout) && is_numeric($previous->timeout)
            ? (int) $previous->timeout
            : 0;

        $connection->statement('PRAGMA busy_timeout = ' . $timeoutMilliseconds);

        return static function () use ($connection, $previousTimeout): void {
            $connection->statement('PRAGMA busy_timeout = ' . $previousTimeout);
        };
    }

    private function applyMySqlTimeout(Connection $connection, int $timeoutMilliseconds): Closure
    {
        $previous = $connection->selectOne(
            'SELECT @@SESSION.innodb_lock_wait_timeout AS innodb_timeout, @@SESSION.lock_wait_timeout AS metadata_timeout',
        );
        $previousInnoDbTimeout = is_object($previous)
            && isset($previous->innodb_timeout)
            && is_numeric($previous->innodb_timeout)
            ? (int) $previous->innodb_timeout
            : 0;
        $previousMetadataTimeout = is_object($previous)
            && isset($previous->metadata_timeout)
            && is_numeric($previous->metadata_timeout)
            ? (int) $previous->metadata_timeout
            : 0;

        if ($previousInnoDbTimeout < 1 || $previousMetadataTimeout < 1) {
            throw new RuntimeException('Unable to read the database lock timeout.');
        }

        $previousStatementTimeout = null;
        $serverVersion = $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        $isMariaDb = is_string($serverVersion) && str_contains(mb_strtolower($serverVersion), 'mariadb');

        if ($isMariaDb) {
            $statementTimeout = $connection->selectOne('SELECT @@SESSION.max_statement_time AS timeout');
            $previousStatementTimeout = is_object($statementTimeout)
                && isset($statementTimeout->timeout)
                && is_numeric($statementTimeout->timeout)
                ? (float) $statementTimeout->timeout
                : null;

            if ($previousStatementTimeout === null || $previousStatementTimeout < 0) {
                throw new RuntimeException('Unable to read the database statement timeout.');
            }
        }

        $timeoutSeconds = max(1, (int) ceil($timeoutMilliseconds / 1000));
        $connection->statement('SET SESSION innodb_lock_wait_timeout = ' . $timeoutSeconds);
        $connection->statement('SET SESSION lock_wait_timeout = ' . $timeoutSeconds);

        if ($isMariaDb) {
            $statementTimeoutSeconds = number_format($timeoutMilliseconds / 1000, 3, '.', '');
            $connection->statement('SET SESSION max_statement_time = ' . $statementTimeoutSeconds);
        }

        return static function () use ($connection, $previousInnoDbTimeout, $previousMetadataTimeout, $previousStatementTimeout): void {
            $connection->statement('SET SESSION innodb_lock_wait_timeout = ' . $previousInnoDbTimeout);
            $connection->statement('SET SESSION lock_wait_timeout = ' . $previousMetadataTimeout);

            if ($previousStatementTimeout !== null) {
                $connection->statement('SET SESSION max_statement_time = ' . number_format($previousStatementTimeout, 3, '.', ''));
            }
        };
    }

    private function positiveConfigInteger(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }
}
