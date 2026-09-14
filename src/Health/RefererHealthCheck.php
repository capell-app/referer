<?php

declare(strict_types=1);

namespace Capell\Referer\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Frontend\Support\Routing\FrontendRouteMiddlewareRegistry;
use Capell\Referer\Actions\RefererHealthSignal;
use Capell\Referer\Http\Middleware\RecordRefererMiddleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class RefererHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }

    /** @return Collection<int, DoctorCheckResultData> */
    public static function runDiagnostics(): Collection
    {
        $check = new self;
        $checks = collect([
            $check->storageCheck(),
            $check->middlewareCheck(),
            $check->writeSignalCheck(),
        ]);

        return $checks->values();
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    private function storageCheck(): DoctorCheckResultData
    {
        try {
            $passed = Schema::hasTable('referer_daily_counts')
                && Schema::hasTable('referer_source_totals')
                && Schema::hasTable('referer_retention_state');
        } catch (Throwable) {
            $passed = false;
        }

        return new DoctorCheckResultData(
            label: (string) __('capell-referer::package.health.storage_label'),
            passed: $passed,
            message: $passed
                ? (string) __('capell-referer::package.health.storage_passed')
                : (string) __('capell-referer::package.health.storage_failed'),
            remediation: $passed ? null : (string) __('capell-referer::package.health.storage_remediation'),
        );
    }

    private function middlewareCheck(): DoctorCheckResultData
    {
        $registered = app()->bound(FrontendRouteMiddlewareRegistry::class)
            && in_array(RecordRefererMiddleware::class, resolve(FrontendRouteMiddlewareRegistry::class)->all(), true);

        return new DoctorCheckResultData(
            label: (string) __('capell-referer::package.health.middleware_label'),
            passed: $registered,
            message: $registered
                ? (string) __('capell-referer::package.health.middleware_passed')
                : (string) __('capell-referer::package.health.middleware_failed'),
            remediation: $registered ? null : (string) __('capell-referer::package.health.middleware_remediation'),
        );
    }

    private function writeSignalCheck(): DoctorCheckResultData
    {
        $failed = app()->bound(RefererHealthSignal::class) && resolve(RefererHealthSignal::class)->hasRecentWriteFailure();

        return new DoctorCheckResultData(
            label: (string) __('capell-referer::package.health.write_label'),
            passed: ! $failed,
            message: $failed
                ? (string) __('capell-referer::package.health.write_failed')
                : (string) __('capell-referer::package.health.write_passed'),
            remediation: $failed ? (string) __('capell-referer::package.health.write_remediation') : null,
        );
    }
}
