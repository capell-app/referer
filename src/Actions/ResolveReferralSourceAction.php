<?php

declare(strict_types=1);

namespace Capell\Referer\Actions;

use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\Referer\Data\ReferralSourceData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\Referer\Sources\RequestHeader;
use Throwable;

final class ResolveReferralSourceAction
{
    use AsObject;

    public function __construct(private readonly RequestHeader $requestHeader) {}

    public function handle(Request $request, Site $site): ?ReferralSourceData
    {
        $rawHeader = $request->header('referer');

        if (! is_string($rawHeader)
            || $rawHeader === ''
            || mb_strlen($rawHeader) > $this->positiveConfigInteger('capell-referer.max_header_length', 2048)
            || preg_match('/[\x00-\x1F\x7F]/', $rawHeader) === 1) {
            return null;
        }

        try {
            $upstreamHost = $this->requestHeader->getReferer($request);
        } catch (Throwable) {
            return null;
        }

        try {
            $parsed = parse_url($rawHeader);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($parsed) || ! in_array($parsed['scheme'] ?? null, ['http', 'https'], true)) {
            return null;
        }

        if (($parsed['user'] ?? null) !== null || ($parsed['pass'] ?? null) !== null) {
            return null;
        }

        $host = $this->normalizeHost($parsed['host'] ?? null);
        $upstreamHost = $this->normalizeHost($upstreamHost);

        if ($host === null || $upstreamHost === null || $host !== $upstreamHost || $this->isCurrentSiteHost($host, $request, $site)) {
            return null;
        }

        $mapping = $this->sourceMapping();

        foreach ($mapping as $sourceKey => $hosts) {
            foreach ($hosts as $mappedHost) {
                if ($host === $mappedHost || str_ends_with($host, '.' . $mappedHost)) {
                    return new ReferralSourceData($sourceKey);
                }
            }
        }

        return new ReferralSourceData('other_external');
    }

    /** @return array<string, list<string>> */
    private function sourceMapping(): array
    {
        $configured = config('capell-referer.source_mappings', []);

        if (! is_array($configured)) {
            return [];
        }

        $mapping = [];
        $maximum = $this->positiveConfigInteger('capell-referer.max_source_mappings', 500);

        foreach ($configured as $key => $hosts) {
            if (! is_string($key) || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $key) || $key === 'other_external' || count($mapping) >= $maximum || ! is_array($hosts)) {
                continue;
            }

            $normalizedHosts = [];
            foreach ($hosts as $configuredHost) {
                $normalizedHost = $this->normalizeHost($configuredHost);
                if ($normalizedHost !== null) {
                    $normalizedHosts[] = $normalizedHost;
                }
            }

            if ($normalizedHosts !== []) {
                $mapping[$key] = array_values(array_unique($normalizedHosts));
            }
        }

        return $mapping;
    }

    private function isCurrentSiteHost(string $host, Request $request, Site $site): bool
    {
        $currentHosts = [$this->normalizeHost($request->getHost())];

        if ($site->relationLoaded('siteDomains')) {
            $domains = $site->getRelation('siteDomains');
            if ($domains instanceof Collection) {
                foreach ($domains as $domain) {
                    if ($domain instanceof SiteDomain) {
                        $currentHosts[] = $this->normalizeHost($domain->domain);
                    }
                }
            }
        }

        if ($site->relationLoaded('siteDomain')) {
            $domain = $site->getRelation('siteDomain');
            if ($domain instanceof SiteDomain) {
                $currentHosts[] = $this->normalizeHost($domain->domain);
            }
        }

        return in_array($host, array_filter($currentHosts), true);
    }

    private function normalizeHost(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > 253 || filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        if (str_ends_with($value, '..')) {
            return null;
        }

        $host = mb_strtolower(rtrim($value, '.'));

        if ($host === '') {
            return null;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if (! is_string($ascii) || $ascii === '') {
                return null;
            }

            $host = mb_strtolower(rtrim($ascii, '.'));
        }

        if ($this->isPrivateHostName($host)) {
            return null;
        }

        $labels = explode('.', $host);
        $topLevelDomain = end($labels);

        if (! is_string($topLevelDomain)
            || mb_strlen($topLevelDomain) < 2
            || ! preg_match('/[a-z]/', $topLevelDomain)
            || mb_strlen($host) > 253
            || ! preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
            return null;
        }

        return $host;
    }

    private function isPrivateHostName(string $host): bool
    {
        if (! str_contains($host, '.')) {
            return true;
        }

        return array_any(
            ['.localhost', '.local', '.internal', '.lan', '.home', '.home.arpa', '.test'],
            fn (string $suffix): bool => $host === ltrim($suffix, '.') || str_ends_with($host, $suffix),
        );
    }

    private function positiveConfigInteger(string $key, int $fallback): int
    {
        $value = config($key, $fallback);

        return is_int($value) && $value > 0 ? $value : $fallback;
    }
}
