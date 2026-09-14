<?php

declare(strict_types=1);

namespace Capell\Referer\Filament\Widgets;

use Capell\Admin\Contracts\CapellFilamentWidgetContract;
use Capell\Admin\Support\SiteScope;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Site;
use Capell\Referer\Actions\BuildRefererReportAction;
use Capell\Referer\Actions\RefererHealthSignal;
use Capell\Referer\Actions\ResolveRefererWindowAction;
use Capell\Referer\Filament\Pages\RefererPage;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;
use Override;
use Spatie\Permission\PermissionRegistrar;

final class TopRefererSourcesFilamentWidget extends BaseWidget implements CapellFilamentWidgetContract
{
    /** @var int|string|array<string, int|null> */
    protected int|string|array $columnSpan = ['md' => 1];

    protected static ?int $sort = 7;

    private bool $reportUnavailable = false;

    #[Override]
    public static function canView(): bool
    {
        return CapellCore::isPackageInstalled('capell-app/referer')
            && (auth()->user()?->can('View:RefererPage') ?? false);
    }

    public static function settingsKey(): string
    {
        return 'referer_top_sources';
    }

    /** @return list<string> */
    public static function rolesConfigKeys(): array
    {
        return [];
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => $this->records())
            ->queryStringIdentifier('referer-top-sources')
            ->paginated(false)
            ->searchable(false)
            ->heading(__('capell-referer::report.top_sources'))
            ->emptyStateHeading(fn (): string => $this->reportUnavailable || resolve(RefererHealthSignal::class)->hasRecentWriteFailure()
                ? __('capell-referer::report.unavailable')
                : __('capell-referer::report.empty'))
            ->columns([
                TextColumn::make('label')->label(__('capell-referer::report.source')),
                TextColumn::make('count')->label(__('capell-referer::report.requests'))->numeric(),
                TextColumn::make('share')->label(__('capell-referer::report.share'))->suffix('%')->numeric(1),
            ])
            ->headerActions([
                Action::make('view-all')
                    ->label(__('capell-admin::button.view_all'))
                    ->button()
                    ->color('gray')
                    ->url(fn (): string => $this->allReportsUrl()),
            ]);
    }

    /** @return Collection<int, array{id: string, label: string, count: int, share: float}> */
    private function records(): Collection
    {
        $site = $this->currentSite();

        if (! $site instanceof Site) {
            return collect();
        }

        $window = ResolveRefererWindowAction::run('last-30-days');

        $report = BuildRefererReportAction::run($site, $window);
        $this->reportUnavailable = ! $report->available;

        return $report->rows
            ->take(5)
            ->map(static fn (mixed $row): array => [
                'id' => 'referer-' . $row->sourceKey,
                'label' => $row->label,
                'count' => $row->count,
                'share' => $row->share,
            ])
            ->values();
    }

    private function currentSite(): ?Site
    {
        $sites = SiteScope::applyForCurrentActor(Site::query(), 'id', denyWhenMissingActor: true)->get();
        $activeSiteId = resolve(PermissionRegistrar::class)->getPermissionsTeamId();
        $site = $sites->firstWhere('id', $activeSiteId) ?? $sites->first();

        return $site instanceof Site ? $site : null;
    }

    private function allReportsUrl(): string
    {
        $site = $this->currentSite();
        $window = ResolveRefererWindowAction::run('last-30-days');

        return RefererPage::getUrl([
            'siteId' => $site?->getKey(),
            'preset' => 'last-30-days',
            'startsOn' => $window->startsOn?->toDateString(),
            'endsOn' => $window->endsOn?->toDateString(),
        ]);
    }
}
