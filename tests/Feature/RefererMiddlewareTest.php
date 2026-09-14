<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Frontend\Contracts\FrontendContextReader;
use Capell\Referer\Actions\RecordRefererCountAction;
use Capell\Referer\Actions\ResolveReferralSourceAction;
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

    $middleware = new RecordRefererMiddleware(
        $context,
        resolve(ResolveReferralSourceAction::class),
        resolve(RecordRefererCountAction::class),
    );
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
    $middleware = new RecordRefererMiddleware(
        $context,
        resolve(ResolveReferralSourceAction::class),
        resolve(RecordRefererCountAction::class),
    );

    $asset = Request::create('/build/app.js', 'GET', [], [], [], ['HTTP_REFERER' => 'https://www.google.com']);
    $redirect = Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'https://www.google.com']);
    $json = Request::create('/', 'GET', [], [], [], ['HTTP_REFERER' => 'https://www.google.com']);

    $next = static fn (): Response => new Response('', Response::HTTP_FOUND, ['Location' => '/next']);
    $middleware->handle($asset, $next);
    $middleware->handle($redirect, $next);
    $middleware->handle($json, static fn (): Response => new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']));

    expect(DB::table('referer_daily_counts')->count())->toBe(0);
});
