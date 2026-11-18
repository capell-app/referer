<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\ScreenshotFixtures\FixtureState;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class SeedRefererScreenshotFixtureAction
{
    use AsFake;
    use AsObject;

    public function handle(): int
    {
        $configuredPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
        $appPath = is_string($configuredPath) ? realpath($configuredPath) : false;

        throw_unless(
            in_array(config('app.env'), ['local', 'testing'], true)
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($appPath)
                && $appPath === realpath(base_path())
                && (class_exists(FixtureState::class) && FixtureState::isDisposableApplication(realpath(base_path()))),
            RuntimeException::class,
            'Referer screenshot fixtures require the explicit disposable local screenshot environment.',
        );

        FixtureState::boot();

        // Seed every disposable site so the report works with the capture
        // administrator's actual site scope, without changing permissions.
        return SeedRefererDemoCountsAction::run();
    }
}
