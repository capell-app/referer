<?php

declare(strict_types=1);

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Models\Site;
use Capell\Referer\Actions\RefererHealthSignal;
use Capell\Referer\Data\RefererWindowData;
use Capell\Referer\Enums\RefererPermission;
use Capell\Referer\Filament\Pages\RefererPage;
use Capell\Referer\Health\RefererHealthCheck;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-09-14 12:00:00 UTC');
    Cache::flush();

    foreach (['referer_daily_counts', 'referer_source_totals', 'referer_retention_state'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('referer_daily_counts', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('site_id');
        $table->date('date');
        $table->string('source_key', 64);
        $table->unsignedBigInteger('count')->default(0);
        $table->unique(['site_id', 'date', 'source_key']);
    });
    Schema::create('referer_source_totals', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('site_id');
        $table->string('source_key', 64);
        $table->unsignedBigInteger('count')->default(0);
        $table->date('collection_started_on');
        $table->unique(['site_id', 'source_key']);
    });
    Schema::create('referer_retention_state', function (Blueprint $table): void {
        $table->unsignedTinyInteger('id')->primary();
        $table->date('daily_available_from');
    });
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** @return array<string, DoctorCheckResultData> */
function refererChecks(): array
{
    $checks = [];

    foreach (RefererHealthCheck::runDiagnostics() as $check) {
        $checks[$check->id] = $check;
    }

    return $checks;
}

it('records a write failure once per window and reports it', function (): void {
    $log = Log::spy();
    $signal = new RefererHealthSignal;

    expect($signal->hasRecentWriteFailure())->toBeFalse();

    $signal->recordWriteFailure();
    $signal->recordWriteFailure();

    expect($signal->hasRecentWriteFailure())->toBeTrue();
    $log->shouldHaveReceived('warning')->once();
});

it('falls back to the default window when the configured signal lifetime is invalid', function (): void {
    config()->set('capell-referer.health_signal_ttl_seconds', -5);

    (new RefererHealthSignal)->recordWriteFailure();
    $this->travel(301)->seconds();

    expect((new RefererHealthSignal)->hasRecentWriteFailure())->toBeFalse();
});

it('never lets a failing cache break the request that is being measured', function (): void {
    Cache::shouldReceive('add')->andThrow(new RuntimeException('cache down'));
    Cache::shouldReceive('has')->andThrow(new RuntimeException('cache down'));
    $signal = new RefererHealthSignal;

    $signal->recordWriteFailure();

    expect($signal->hasRecentWriteFailure())->toBeFalse();
});

it('reports healthy storage and write signals when nothing has failed', function (): void {
    $results = refererChecks();

    expect($results['legacy.referrer.counter.storage']->passed)->toBeTrue()
        ->and($results['legacy.referrer.counter.writes']->passed)->toBeTrue()
        ->and($results['legacy.referrer.counter.writes']->remediation)->toBeNull()
        ->and(RefererHealthCheck::compatibleCapellApiVersion())->toBe('^1.0');
});

it('fails the health check with remediation when storage is missing or writes recently failed', function (): void {
    Schema::dropIfExists('referer_retention_state');
    resolve(RefererHealthSignal::class)->recordWriteFailure();

    $results = refererChecks();

    expect($results['legacy.referrer.counter.storage']->passed)->toBeFalse()
        ->and($results['legacy.referrer.counter.storage']->remediation)->not->toBeNull()
        ->and($results['legacy.referrer.counter.writes']->passed)->toBeFalse()
        ->and($results['legacy.referrer.counter.writes']->remediation)->not->toBeNull()
        ->and(RefererHealthCheck::passed())->toBeFalse();
});

it('prunes expired daily counters through the console command and supports a dry run', function (): void {
    DB::table('referer_daily_counts')->insert([
        ['site_id' => 7, 'date' => '2025-08-10', 'source_key' => 'google', 'count' => 3],
        ['site_id' => 7, 'date' => '2026-09-13', 'source_key' => 'google', 'count' => 4],
    ]);

    $this->artisan('capell:referer:prune', ['--days' => 30, '--dry-run' => true])
        ->expectsOutput('1 referral counter rows eligible.')
        ->assertSuccessful();
    expect(DB::table('referer_daily_counts')->count())->toBe(2);

    $this->artisan('capell:referer:prune', ['--days' => 30])
        ->expectsOutput('1 referral counter rows deleted.')
        ->assertSuccessful();
    expect(DB::table('referer_daily_counts')->pluck('date')->all())->toBe(['2026-09-13']);
});

it('rejects non-positive retention days or batch sizes in the prune command', function (array $options): void {
    DB::table('referer_daily_counts')->insert(['site_id' => 7, 'date' => '2025-08-10', 'source_key' => 'google', 'count' => 3]);

    $this->artisan('capell:referer:prune', $options)
        ->expectsOutput('Days and batch must be positive integers.')
        ->assertFailed();

    expect(DB::table('referer_daily_counts')->count())->toBe(1);
})->with([
    'zero days' => [['--days' => 0]],
    'zero batch' => [['--batch' => 0]],
]);

it('exposes the page permission names', function (): void {
    expect(RefererPermission::names())->toBe(['View:RefererPage']);
});

it('validates referral window invariants and builds distinct cache keys', function (): void {
    $start = CarbonImmutable::parse('2026-09-01');
    $end = CarbonImmutable::parse('2026-09-07');

    expect((new RefererWindowData($start, $end))->cacheKey())->toBe('2026-09-01:2026-09-07')
        ->and((new RefererWindowData(null, null, true))->cacheKey())->toBe('all-time')
        ->and(fn (): RefererWindowData => new RefererWindowData($start, $end, true))->toThrow(InvalidArgumentException::class)
        ->and(fn (): RefererWindowData => new RefererWindowData($start, null))->toThrow(InvalidArgumentException::class)
        ->and(fn (): RefererWindowData => new RefererWindowData($end, $start))->toThrow(InvalidArgumentException::class);
});

it('declares every manifest contribution against the supported Capell API range', function (string $contribution): void {
    expect(class_exists($contribution))->toBeTrue();
    expect((new ReflectionMethod($contribution, 'compatibleCapellApiVersion'))->invoke(null))->toBe('^1.0');
})->with(array_map(
    static fn (string $file): array => ['Capell\\Referer\\Manifest\\' . basename($file, '.php')],
    glob(dirname(__DIR__, 2) . '/src/Manifest/*.php') ?: [],
));

describe('page filters', function (): void {
    beforeEach(function (): void {
        $panel = Panel::make()->id('admin')->path('admin')->default();
        Filament::registerPanel($panel);
        Filament::setCurrentPanel($panel);
        Filament::bootCurrentPanel();
        Filament::setServingStatus();
        Route::get('/admin/referer', static fn (): string => '')->name('filament.admin.pages.referer');

        Permission::findOrCreate('View:RefererPage', 'web');
        $this->actingAsAdmin();
    });

    it('rejects an invalid preset when filters are applied and keeps the report empty', function (): void {
        Site::factory()->create();

        Livewire::test(RefererPage::class)
            ->set('preset', 'not-a-preset')
            ->call('applyFilters')
            ->assertHasErrors(['preset']);
    });

    it('requires both dates for a custom range and rejects malformed ones on refresh', function (): void {
        Site::factory()->create();

        Livewire::test(RefererPage::class)
            ->set('preset', 'custom')
            ->set('startsOn', '')
            ->set('endsOn', '')
            ->call('refreshReport')
            ->assertHasErrors(['startsOn', 'endsOn'])
            ->set('startsOn', '14/09/2026')
            ->call('refreshReport')
            ->assertHasErrors(['startsOn']);
    });

    it('drops the cached report for the selected site when refreshed', function (): void {
        $site = Site::factory()->create();
        DB::table('referer_daily_counts')->insert(['site_id' => $site->getKey(), 'date' => '2026-09-14', 'source_key' => 'google', 'count' => 3]);
        DB::table('referer_source_totals')->insert(['site_id' => $site->getKey(), 'source_key' => 'google', 'count' => 3, 'collection_started_on' => '2026-09-14']);

        Livewire::test(RefererPage::class)
            ->set('siteId', (int) $site->getKey())
            ->assertSee('Google');

        DB::table('referer_daily_counts')->where('source_key', 'google')->update(['source_key' => 'bing']);
        DB::table('referer_source_totals')->where('source_key', 'google')->update(['source_key' => 'bing']);

        Livewire::test(RefererPage::class)
            ->set('siteId', (int) $site->getKey())
            ->call('refreshReport')
            ->assertSee('Bing');
    });

    it('shows a validation error instead of a report when the custom window is reversed', function (): void {
        $site = Site::factory()->create();

        Livewire::test(RefererPage::class)
            ->set('siteId', (int) $site->getKey())
            ->set('preset', 'custom')
            ->set('startsOn', '2026-09-10')
            ->set('endsOn', '2026-09-01')
            ->call('refreshReport')
            ->assertHasErrors();
    });
});
