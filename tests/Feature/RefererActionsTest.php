<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Referer\Actions\BuildRefererReportAction;
use Capell\Referer\Actions\PruneRefererCountsAction;
use Capell\Referer\Actions\RecordRefererCountAction;
use Capell\Referer\Actions\ResolveRefererWindowAction;
use Capell\Referer\Actions\ResolveReferralSourceAction;
use Capell\Referer\Data\RefererReportRowData;
use Capell\Referer\Data\ReferralSourceData;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Schema::dropIfExists('referer_daily_counts');
    Schema::dropIfExists('referer_source_totals');
    Schema::dropIfExists('referer_retention_state');

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

    Cache::flush();
    CarbonImmutable::setTestNow('2026-09-14 12:00:00 UTC');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('normalises recognised, unknown, and unsafe referral headers without persisting the header', function (): void {
    $site = new Site;
    $site->setAttribute('id', 7);

    $resolve = resolve(ResolveReferralSourceAction::class);

    expect($resolve->handle(requestWithReferer('https://www.google.co.uk/search?q=capell'), $site)?->key)
        ->toBe('google')
        ->and($resolve->handle(requestWithReferer('https://example.org/news'), $site)?->key)
        ->toBe('other_external')
        ->and($resolve->handle(requestWithReferer('https://user:pass@example.org/news'), $site))
        ->toBeNull()
        ->and($resolve->handle(requestWithReferer('http://127.0.0.1/private'), $site))
        ->toBeNull()
        ->and($resolve->handle(requestWithReferer(str_repeat('a', 2049)), $site))
        ->toBeNull()
        ->and($resolve->handle(requestWithReferer('https://www.google.com.'), $site)?->key)
        ->toBe('google')
        ->and($resolve->handle(requestWithReferer('https://www.google.com.../'), $site))
        ->toBeNull()
        ->and($resolve->handle(requestWithReferer("https://www.google.com/line\nitem"), $site))
        ->toBeNull()
        ->and($resolve->handle(requestWithReferer('https://xn--e1afmkfd.xn--p1ai/'), $site)?->key)
        ->toBe('other_external')
        ->and($resolve->handle(requestWithReferer('https://machine.ｌｏｃａｌ/'), $site))
        ->toBeNull()
        ->and($resolve->handle(requestWithReferer('http://[::1'), $site))
        ->toBeNull()
        ->and($resolve->handle(requestWithReferer('https://www.example.com/from-alias'), $site))
        ->toBeNull();

    $site->setRelation('siteDomains', collect([
        new SiteDomain(['domain' => 'alias.example.net']),
    ]));

    expect($resolve->handle(requestWithReferer('https://alias.example.net/page', 'www.other-site.test'), $site))
        ->toBeNull();
});

it('rejects private hosts after IDNA normalisation', function (): void {
    $resolve = resolve(ResolveReferralSourceAction::class);
    $normalizeHost = new ReflectionClass($resolve)->getMethod('normalizeHost');

    expect($normalizeHost->invoke($resolve, 'machine.ｌｏｃａｌ'))->toBeNull();
});

it('increments daily and lifetime counters atomically', function (): void {
    $site = new Site;
    $site->setAttribute('id', 7);

    $source = new ReferralSourceData('google');

    expect(RecordRefererCountAction::run($site, $source))->toBeTrue()
        ->and(RecordRefererCountAction::run($site, $source))->toBeTrue();

    expect(DB::table('referer_daily_counts')->where('site_id', 7)->value('count'))->toBe(2)
        ->and(DB::table('referer_source_totals')->where('site_id', 7)->value('count'))->toBe(2)
        ->and(DB::table('referer_daily_counts')->count())->toBe(1)
        ->and(DB::table('referer_source_totals')->count())->toBe(1);
});

it('rejects source keys outside the configured bounded source set before writing', function (): void {
    $site = new Site;
    $site->setAttribute('id', 7);

    expect(RecordRefererCountAction::run($site, new ReferralSourceData('google')))->toBeTrue()
        ->and(RecordRefererCountAction::run($site, new ReferralSourceData('other_external')))->toBeTrue()
        ->and(RecordRefererCountAction::run($site, new ReferralSourceData('customer-example')))->toBeFalse()
        ->and(RecordRefererCountAction::run($site, new ReferralSourceData('support@example.test')))->toBeFalse()
        ->and(DB::table('referer_daily_counts')->pluck('source_key')->all())->toBe(['google', 'other_external'])
        ->and(DB::table('referer_source_totals')->pluck('source_key')->all())->toBe(['google', 'other_external']);
});

it('resolves bounded UTC windows and rejects expired or future custom dates', function (): void {
    expect(ResolveRefererWindowAction::run('last-30-days')->cacheKey())->toBe('2026-08-16:2026-09-14')
        ->and(ResolveRefererWindowAction::run('all-time')->allTime)->toBeTrue();

    expect(fn (): mixed => ResolveRefererWindowAction::run('custom', '2025-01-01', '2025-01-02'))
        ->toThrow(ValidationException::class);

    expect(fn (): mixed => ResolveRefererWindowAction::run('custom', '2026-09-01', '2026-09-15'))
        ->toThrow(ValidationException::class);

    CarbonImmutable::setTestNow('2026-03-31 12:00:00 UTC');

    expect(ResolveRefererWindowAction::run('previous-month')->cacheKey())->toBe('2026-02-01:2026-02-28');
});

it('builds a cached source report with the other external bucket in the denominator', function (): void {
    $site = new Site;
    $site->setAttribute('id', 7);

    DB::table('referer_daily_counts')->insert([
        ['site_id' => 7, 'date' => '2026-09-14', 'source_key' => 'google', 'count' => 3],
        ['site_id' => 7, 'date' => '2026-09-14', 'source_key' => 'other_external', 'count' => 1],
    ]);
    DB::table('referer_source_totals')->insert([
        ['site_id' => 7, 'source_key' => 'google', 'count' => 8, 'collection_started_on' => '2026-08-01'],
        ['site_id' => 7, 'source_key' => 'other_external', 'count' => 2, 'collection_started_on' => '2026-08-10'],
    ]);

    $report = BuildRefererReportAction::run($site, ResolveRefererWindowAction::run('last-30-days'));

    /** @var RefererReportRowData $firstRow */
    $firstRow = $report->rows->firstOrFail();

    expect($report->available)->toBeTrue()
        ->and($report->measuredRequests)->toBe(4)
        ->and($report->rows->pluck('sourceKey')->all())->toBe(['google', 'other_external'])
        ->and($firstRow->share)->toBe(75.0)
        ->and($report->collectionStartedOn?->toDateString())->toBe('2026-08-01')
        ->and($report->availableThroughOn?->toDateString())->toBe('2026-09-14');

    expect(BuildRefererReportAction::run($site, ResolveRefererWindowAction::run('all-time'))->measuredRequests)->toBe(10);
});

it('marks reports unavailable while collection has a recent write failure', function (): void {
    $site = new Site;
    $site->setAttribute('id', 7);
    Cache::put('capell-referer.health.write-failure', true, 60);

    expect(BuildRefererReportAction::run($site, ResolveRefererWindowAction::run('last-30-days'))->available)
        ->toBeFalse();
});

it('does not rebuild a report while its refresh lock is held', function (): void {
    $site = new Site;
    $site->setAttribute('id', 7);

    $window = ResolveRefererWindowAction::run('last-30-days');
    $lock = Cache::lock(BuildRefererReportAction::lockKey(7, $window), 5);

    expect($lock->get())->toBeTrue();

    expect(BuildRefererReportAction::run($site, $window)->available)->toBeFalse();

    $lock->release();
});

it('prunes only expired daily rows and preserves lifetime totals', function (): void {
    DB::table('referer_daily_counts')->insert([
        ['site_id' => 7, 'date' => '2025-08-10', 'source_key' => 'google', 'count' => 3],
        ['site_id' => 7, 'date' => '2025-08-11', 'source_key' => 'google', 'count' => 4],
        ['site_id' => 7, 'date' => '2026-09-14', 'source_key' => 'google', 'count' => 5],
    ]);
    DB::table('referer_source_totals')->insert([
        'site_id' => 7,
        'source_key' => 'google',
        'count' => 12,
        'collection_started_on' => '2025-08-10',
    ]);

    expect(PruneRefererCountsAction::run(400, 1))->toBe(1)
        ->and(DB::table('referer_daily_counts')->where('date', '2025-08-10')->exists())->toBeFalse()
        ->and(DB::table('referer_daily_counts')->where('date', '2025-08-11')->exists())->toBeTrue()
        ->and(DB::table('referer_source_totals')->value('count'))->toBe(12);

    expect(fn (): mixed => ResolveRefererWindowAction::run('custom', '2025-08-10', '2025-08-10'))
        ->toThrow(ValidationException::class);
});

it('clamps the dashboard period to the retained daily range', function (): void {
    PruneRefererCountsAction::run(7);

    $window = resolve(ResolveRefererWindowAction::class)->latestAvailable(30);

    expect($window->startsOn?->toDateString())->toBe('2026-09-08')
        ->and($window->endsOn?->toDateString())->toBe('2026-09-14')
        ->and($window->allTime)->toBeFalse();

    PruneRefererCountsAction::run(1);
    $singleDayWindow = resolve(ResolveRefererWindowAction::class)->latestAvailable(30);

    expect($singleDayWindow->startsOn?->toDateString())->toBe('2026-09-14')
        ->and($singleDayWindow->endsOn?->toDateString())->toBe('2026-09-14');
});

it('rolls back daily pruning when the retention boundary cannot be recorded', function (): void {
    DB::table('referer_daily_counts')->insert([
        'site_id' => 7,
        'date' => '2025-08-10',
        'source_key' => 'google',
        'count' => 3,
    ]);
    Schema::dropIfExists('referer_retention_state');

    expect(fn (): mixed => PruneRefererCountsAction::run(400, 1))
        ->toThrow(QueryException::class)
        ->and(DB::table('referer_daily_counts')->where('date', '2025-08-10')->exists())
        ->toBeTrue();
});

function requestWithReferer(string $referer, string $host = 'www.example.com'): Request
{
    return Request::create('/', 'GET', [], [], [], [
        'HTTP_HOST' => $host,
        'HTTP_REFERER' => $referer,
    ]);
}
