<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Foundation\Application;

it('matches fresh installed boot after in-process installation and repeated refresh', function (): void {
    InstalledRuntimeParity::assertPackage('referer', static function (Application $app): void {
        $tables = CapellCore::getProtectedTables();
        $expected = ['referer_daily_counts', 'referer_source_totals', 'referer_retention_state'];

        expect(array_values(array_filter($tables, static fn (mixed $table): bool => in_array($table, $expected, true))))->toBe($expected);
    });
});

it('keeps protected table registrations absent before installation', function (): void {
    InstalledRuntimeState::run(static function (): void {
        InstalledRuntimeTestCase::boot(InstalledRuntimeParity::manifest('referer'), false);
        $tables = CapellCore::getProtectedTables();
        $owned = ['referer_daily_counts', 'referer_source_totals', 'referer_retention_state'];

        expect(array_values(array_filter($tables, static fn (mixed $table): bool => in_array($table, $owned, true))))->toBe([]);
    });
});
