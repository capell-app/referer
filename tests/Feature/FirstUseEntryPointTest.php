<?php

declare(strict_types=1);

use Capell\Referer\Filament\Pages\RefererPage;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Support\Facades\Route;

it('keeps the authored first use entry point on the upstream registered surface', function (): void {
    $panel = Panel::make()->id('first-use')->path('admin');
    resolve(PanelRegistry::class)->register($panel);
    Filament::setCurrentPanel($panel);
    Route::prefix('admin')->name('filament.first-use.')->group(static function () use ($panel): void {
        RefererPage::registerRoutes($panel);
    });
    $journey = json_decode((string) file_get_contents(__DIR__ . '/../../docs/first-use.json'), true, flags: JSON_THROW_ON_ERROR);

    throw_unless(is_array($journey) && is_string($journey['destination'] ?? null), RuntimeException::class, 'Expected a first-use destination.');

    expect(parse_url(RefererPage::getUrl(panel: 'first-use'), PHP_URL_PATH))->toBe($journey['destination']);
});
