<?php

declare(strict_types=1);

use Capell\Referer\Console\Commands\SeedRefererScreenshotFixtureCommand;
use Capell\Tests\Support\CapellManifest;
use Capell\Tests\Support\ScreenshotManifest;

it('declares the fixture command and a required marketplace report capture', function (): void {
    $manifestPath = __DIR__ . '/../../capell.json';
    $screenshotsPath = __DIR__ . '/../../docs/screenshots.json';
    $entry = ScreenshotManifest::entry($screenshotsPath, 'referer-admin-report');

    expect(CapellManifest::command($manifestPath, 'demo'))->toBe('capell:referer:screenshot-fixture')
        ->and(CapellManifest::consoleCommandNames($manifestPath))->toContain('capell:referer:screenshot-fixture')
        ->and(CapellManifest::consoleCommandClasses($manifestPath))->toContain(SeedRefererScreenshotFixtureCommand::class)
        ->and(ScreenshotManifest::fixtureCommands($screenshotsPath))->toContain('capell:referer:screenshot-fixture')
        ->and($entry['required'] ?? null)->toBeTrue()
        ->and($entry['url'])->toBe('/referer')
        ->and($entry['waitFor'] ?? null)->toBe('table tbody tr:has-text("Google"):has-text("128")')
        ->and(data_get($entry, 'entryState.setup.command'))->toBe('capell:referer:screenshot-fixture')
        ->and(data_get($entry, 'entryState.setup.args'))->toBe(['--force'])
        // The capture recipe exists before a real image can be advertised in the Marketplace.
        ->and($entry['screenshotPath'] ?? null)->toBe('packages/referer/docs/screenshots/referer-admin-report.png');
});
