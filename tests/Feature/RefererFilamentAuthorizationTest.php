<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Referer\Filament\Pages\RefererPage;
use Capell\Referer\Filament\Widgets\TopRefererSourcesFilamentWidget;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-09-14 12:00:00 UTC');
    $this->actingAsAdmin();

    $panel = Panel::make()->id('admin')->path('admin')->default();
    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);
    Filament::bootCurrentPanel();
    Filament::setServingStatus();
    Route::get('/admin/referer', static fn (): string => '')
        ->name('filament.admin.pages.referer');

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

    Permission::findOrCreate('View:RefererPage', 'web');
    Cache::flush();
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('denies anonymous and unpermitted access to the page and dashboard widget', function (): void {
    auth()->logout();

    expect(RefererPage::canAccess())->toBeFalse()
        ->and(TopRefererSourcesFilamentWidget::canView())->toBeFalse();

    Livewire::test(RefererPage::class)->assertForbidden();
    Livewire::test(TopRefererSourcesFilamentWidget::class)->assertForbidden();

    $this->actingAsUser();

    expect(RefererPage::canAccess())->toBeFalse()
        ->and(TopRefererSourcesFilamentWidget::canView())->toBeFalse();

    Livewire::test(RefererPage::class)->assertForbidden();
    Livewire::test(TopRefererSourcesFilamentWidget::class)->assertForbidden();
});

it('rechecks page permission on subsequent Livewire requests', function (): void {
    $user = $this->createUserWithPermission('View:RefererPage');
    $this->actingAs($user);
    $page = Livewire::test(RefererPage::class)->assertOk();

    $user->revokePermissionTo('View:RefererPage');
    resolve(PermissionRegistrar::class)->forgetCachedPermissions();

    $page->call('refreshReport')->assertForbidden();
});

it('forbids a permissioned actor from forging an unassigned site', function (): void {
    $site = Site::factory()->create();
    $this->actingAs($this->createUserWithPermission('View:RefererPage'));

    Livewire::test(RefererPage::class)
        ->set('siteId', (int) $site->getKey())
        ->assertForbidden();
});

it('keeps page and widget reports isolated to the selected site', function (): void {
    $firstSite = Site::factory()->create(['name' => 'First referral site']);
    $secondSite = Site::factory()->create(['name' => 'Second referral site']);
    DB::table('referer_daily_counts')->insert([
        ['site_id' => $firstSite->getKey(), 'date' => '2026-09-14', 'source_key' => 'google', 'count' => 3],
        ['site_id' => $secondSite->getKey(), 'date' => '2026-09-14', 'source_key' => 'bing', 'count' => 5],
    ]);
    DB::table('referer_source_totals')->insert([
        ['site_id' => $firstSite->getKey(), 'source_key' => 'google', 'count' => 3, 'collection_started_on' => '2026-09-14'],
        ['site_id' => $secondSite->getKey(), 'source_key' => 'bing', 'count' => 5, 'collection_started_on' => '2026-09-14'],
    ]);

    Livewire::test(RefererPage::class)
        ->set('siteId', (int) $firstSite->getKey())
        ->assertSee('Google')
        ->assertDontSee('Bing')
        ->set('siteId', (int) $secondSite->getKey())
        ->assertSee('Bing')
        ->assertDontSee('Google');

    $registrar = resolve(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($firstSite->getKey());
    Livewire::test(TopRefererSourcesFilamentWidget::class)
        ->assertSee('Google')
        ->assertDontSee('Bing');

    $registrar->setPermissionsTeamId($secondSite->getKey());
    Livewire::test(TopRefererSourcesFilamentWidget::class)
        ->assertSee('Bing')
        ->assertDontSee('Google');
});
