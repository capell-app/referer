<?php

declare(strict_types=1);

use Capell\Frontend\Support\Routing\FrontendRouteMiddlewareRegistry;
use Capell\Referer\Http\Middleware\RecordRefererMiddleware;

it('registers collection after the application cache middleware', function (): void {
    $middleware = resolve(FrontendRouteMiddlewareRegistry::class)->all();
    $collectorPosition = array_search(RecordRefererMiddleware::class, $middleware, true);
    $cachePosition = array_search('frontend.anonymous_cacheable_render', $middleware, true);

    if (! is_int($collectorPosition) || ! is_int($cachePosition)) {
        throw new LogicException('The frontend middleware registry is missing the expected entries.');
    }

    expect($collectorPosition)->toBeGreaterThan($cachePosition);
});
