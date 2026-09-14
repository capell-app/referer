<?php

declare(strict_types=1);

namespace Capell\Referer\Filament\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Site;
use Capell\Referer\Actions\BuildRefererReportAction;
use Capell\Referer\Actions\ResolveRefererWindowAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Override;
use Spatie\Permission\PermissionRegistrar;

final class RefererPage extends Page
{
    use HasPageShield;

    #[Url]
    public ?int $siteId = null;

    #[Url]
    public string $preset = 'last-30-days';

    #[Url]
    public string $startsOn = '';

    #[Url]
    public string $endsOn = '';

    public int $reportPage = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static ?int $navigationSort = 4;

    protected string $view = 'capell-referer::filament.pages.referer';

    protected static ?string $slug = 'referer';

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('capell-referer::package.navigation');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('capell-admin::navigation.group_monitoring');
    }

    #[Override]
    public static function canAccess(): bool
    {
        return auth()->user()?->can('View:RefererPage') ?? false;
    }

    #[Override]
    public function getTitle(): string
    {
        return __('capell-referer::package.title');
    }

    #[Override]
    public function getSubheading(): string
    {
        return __('capell-referer::package.subtitle');
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);

        $this->startsOn = $this->startsOn !== '' ? $this->startsOn : now('UTC')->subDays(29)->toDateString();
        $this->endsOn = $this->endsOn !== '' ? $this->endsOn : now('UTC')->toDateString();
        $this->siteId ??= $this->initialSiteId();
    }

    public function applyFilters(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->resetErrorBag();
        $this->reportPage = 1;
        $this->validateFilters();
    }

    public function refreshReport(): void
    {
        abort_unless(self::canAccess(), 403);

        try {
            $this->validateFilters();
            $window = ResolveRefererWindowAction::run($this->preset, $this->startsOn, $this->endsOn);
            $siteId = $this->siteId;

            if ($siteId !== null) {
                $site = SiteScope::applyForCurrentActor(
                    Site::query()->whereKey($siteId),
                    'id',
                    denyWhenMissingActor: true,
                )->first();

                if ($site instanceof Site) {
                    Cache::lock(BuildRefererReportAction::lockKey($siteId, $window), 5)->get(
                        function () use ($siteId, $window): bool {
                            Cache::forget(BuildRefererReportAction::cacheKey($siteId, $window));

                            return true;
                        },
                    );
                }
            }
        } catch (ValidationException $validationException) {
            $this->setErrorBag(new MessageBag($validationException->errors()));
        }
    }

    /** @return array<string, mixed> */
    #[Override]
    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);

        $sites = SiteScope::applyForCurrentActor(Site::query()->select(['id', 'name']), 'id', denyWhenMissingActor: true)->get();
        $site = $sites->firstWhere('id', $this->siteId);
        abort_if($this->siteId !== null && ! $site instanceof Site, 403);

        $viewData = [
            'sites' => $sites->mapWithKeys(fn (Site $site): array => [$site->id => $site->name])->all(),
            'presetOptions' => collect(ResolveRefererWindowAction::PRESETS)
                ->mapWithKeys(fn (string $preset): array => [$preset => __('capell-referer::report.presets.' . $preset)])
                ->all(),
            'report' => null,
        ];

        if (! $site instanceof Site) {
            return $viewData;
        }

        try {
            $this->validateFilters();
            $window = ResolveRefererWindowAction::run($this->preset, $this->startsOn, $this->endsOn);
        } catch (ValidationException $validationException) {
            $this->setErrorBag(new MessageBag($validationException->errors()));

            return $viewData;
        }

        $report = BuildRefererReportAction::run($site, $window);
        $pages = max(1, (int) ceil($report->rows->count() / 25));
        $page = min(max(1, $this->reportPage), $pages);

        return [...$viewData,
            'report' => $report,
            'reportRows' => $report->rows->forPage($page, 25),
            'reportPage' => $page,
            'reportPages' => $pages,
        ];
    }

    private function validateFilters(): void
    {
        $siteIds = SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->pluck('id')->map(
            static fn (mixed $id): int => is_int($id) ? $id : (is_string($id) && is_numeric($id) ? (int) $id : 0),
        )->all();

        $this->validate([
            'siteId' => ['nullable', 'integer', Rule::in($siteIds)],
            'preset' => ['required', Rule::in(ResolveRefererWindowAction::PRESETS)],
            'startsOn' => [Rule::requiredIf($this->preset === 'custom'), 'nullable', 'date_format:Y-m-d'],
            'endsOn' => [Rule::requiredIf($this->preset === 'custom'), 'nullable', 'date_format:Y-m-d'],
        ]);
    }

    private function initialSiteId(): ?int
    {
        $siteIds = SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->pluck('id');
        $activeSiteId = resolve(PermissionRegistrar::class)->getPermissionsTeamId();

        $selected = $siteIds->contains($activeSiteId) ? $activeSiteId : $siteIds->first();

        return is_numeric($selected) ? (int) $selected : null;
    }
}
