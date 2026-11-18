<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Referer\Http\Middleware\RecordRefererMiddleware;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

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
});

it('records an external referrer after a successful HTML origin response without changing the response', function (): void {
    $site = new Site;
    $site->setAttribute('id', 12);

    $context = Mockery::mock(FrontendContextReader::class);
    $context->shouldReceive('site')->once()->andReturn($site);
    $context->shouldReceive('isError')->once()->andReturnFalse();

    $middleware = new RecordRefererMiddleware($context);
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_HOST' => 'www.example.com',
        'HTTP_REFERER' => 'https://www.google.com/search?q=capell',
    ]);

    $response = $middleware->handle($request, static fn (): Response => new Response(
        '<html><body>OK</body></html>',
        Response::HTTP_OK,
        ['Content-Type' => 'text/html; charset=UTF-8'],
    ));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and(DB::table('referer_daily_counts')->where('site_id', 12)->value('count'))->toBe(1);
});

it('does not collect assets, redirects, or non-HTML responses', function (): void {
    $context = Mockery::mock(FrontendContextReader::class);
    $context->shouldReceive('site')->never();
    $context->shouldReceive('isError')->never();
    $middleware = new RecordRefererMiddleware($context);

    $asset = Request::create('/build/app.js', 'GET', [], [], [], ['HTTP_REFERER' => 'https://www.google.com']);
    $redirect = Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'https://www.google.com']);
    $json = Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'https://www.google.com']);

    $next = static fn (): Response => new Response('', Response::HTTP_FOUND, ['Location' => '/next']);
    $middleware->handle($asset, $next);
    $middleware->handle($redirect, $next);
    $middleware->handle($json, static fn (): Response => new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']));

    expect(DB::table('referer_daily_counts')->count())->toBe(0);
});

it('does not count crawlers, prefetches or prerenders', function (array $server): void {
    $context = Mockery::mock(FrontendContextReader::class);
    $context->shouldReceive('site')->never();
    $middleware = new RecordRefererMiddleware($context);

    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_HOST' => 'www.example.com',
        'HTTP_REFERER' => 'https://www.google.com/search?q=capell',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/130.0 Safari/537.36',
        ...$server,
    ]);

    $middleware->handle($request, static fn (): Response => new Response('<html></html>', Response::HTTP_OK, ['Content-Type' => 'text/html']));

    expect(DB::table('referer_daily_counts')->count())->toBe(0);
})->with([
    'search crawler' => [['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']],
    'link preview' => [['HTTP_USER_AGENT' => 'facebookexternalhit/1.1']],
    'empty user agent' => [['HTTP_USER_AGENT' => '']],
    'sec-purpose prefetch' => [['HTTP_SEC_PURPOSE' => 'prefetch']],
    'sec-purpose prerender' => [['HTTP_SEC_PURPOSE' => 'prefetch;prerender']],
    'legacy purpose header' => [['HTTP_PURPOSE' => 'prefetch']],
]);

it('skips requests that are not public page views', function (string $method, string $uri, array $server, bool $enabled): void {
    config()->set('capell-referer.enabled', $enabled);
    $context = Mockery::mock(FrontendContextReader::class);
    $context->shouldReceive('site')->never();
    $middleware = new RecordRefererMiddleware($context);

    $request = Request::create($uri, $method, [], [], [], [
        'HTTP_HOST' => 'www.example.com',
        'HTTP_REFERER' => 'https://www.google.com/search?q=capell',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/130.0 Safari/537.36',
        ...$server,
    ]);

    $middleware->handle($request, static fn (): Response => new Response('<html></html>', Response::HTTP_OK, ['Content-Type' => 'text/html']));

    expect(DB::table('referer_daily_counts')->count())->toBe(0);
})->with([
    'collection disabled' => ['GET', '/', [], false],
    'form submission' => ['POST', '/', [], true],
    'livewire update' => ['GET', '/', ['HTTP_X_LIVEWIRE' => 'true'], true],
    'inertia visit' => ['GET', '/', ['HTTP_X_INERTIA' => 'true'], true],
    'admin area' => ['GET', '/admin/pages', [], true],
    'preview query' => ['GET', '/?preview=1', [], true],
    'api path' => ['GET', '/api/pages', [], true],
]);

it('does not count error pages or requests without a resolved site', function (bool $hasSite, bool $isError): void {
    $site = new Site;
    $site->setAttribute('id', 12);
    $context = Mockery::mock(FrontendContextReader::class);
    $context->shouldReceive('site')->andReturn($hasSite ? $site : null);
    $context->shouldReceive('isError')->andReturn($isError);
    $middleware = new RecordRefererMiddleware($context);

    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_HOST' => 'www.example.com',
        'HTTP_REFERER' => 'https://www.google.com/search?q=capell',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/130.0 Safari/537.36',
    ]);

    $response = $middleware->handle($request, static fn (): Response => new Response('<html>page</html>', Response::HTTP_OK, ['Content-Type' => 'text/html']));

    expect($response->getContent())->toBe('<html>page</html>')
        ->and(DB::table('referer_daily_counts')->count())->toBe(0);
})->with([
    'no site' => [false, false],
    'error page' => [true, true],
]);

it('never lets a measurement failure change page delivery', function (): void {
    $context = Mockery::mock(FrontendContextReader::class);
    $context->shouldReceive('site')->andThrow(new RuntimeException('context unavailable'));
    $middleware = new RecordRefererMiddleware($context);

    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_HOST' => 'www.example.com',
        'HTTP_REFERER' => 'https://www.google.com/search?q=capell',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/130.0 Safari/537.36',
    ]);

    $response = $middleware->handle($request, static fn (): Response => new Response('<html>page</html>', Response::HTTP_OK, ['Content-Type' => 'text/html']));

    expect($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->getContent())->toBe('<html>page</html>');
});
