<?php

declare(strict_types=1);

namespace Capell\Referer\Http\Middleware;

use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Referer\Actions\RecordRefererCountAction;
use Capell\Referer\Actions\ResolveReferralSourceAction;
use Capell\Referer\Data\ReferralSourceData;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordRefererMiddleware
{
    public function __construct(
        private readonly FrontendContextReader $frontendContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldInspect($request)) {
            return $next($request);
        }

        $response = $next($request);

        if ($response->getStatusCode() !== Response::HTTP_OK
            || $response->isRedirection()
            || ! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')) {
            return $response;
        }

        try {
            $site = $this->frontendContext->site();

            if (! $site instanceof Site || $this->frontendContext->isError()) {
                return $response;
            }

            $source = ResolveReferralSourceAction::run($request, $site);

            if ($source instanceof ReferralSourceData) {
                RecordRefererCountAction::run($site, $source);
            }
        } catch (Throwable) {
            // Referral measurement is strictly best-effort and cannot change page delivery.
        }

        return $response;
    }

    private function shouldInspect(Request $request): bool
    {
        if (config('capell-referer.enabled', true) !== true || ! $request->isMethod('GET')) {
            return false;
        }

        if ($request->hasHeader('X-Livewire') || $request->hasHeader('X-Inertia')) {
            return false;
        }

        $path = trim($request->path(), '/');

        if ($request->query('preview') !== null
            || $request->routeIs('*preview*')
            || preg_match('#^(?:admin|api|livewire|health|healthz|up|status|metrics|preview|_debugbar)(?:/|$)#i', $path)) {
            return false;
        }

        return ! preg_match('#\.(?:css|js|map|json|xml|txt|ico|png|jpe?g|gif|svg|webp|avif|woff2?|ttf|otf|pdf|zip)$#i', $path);
    }
}
