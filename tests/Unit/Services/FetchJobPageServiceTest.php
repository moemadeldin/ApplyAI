<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\FetchJobPageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

test('fetches job page content via Jina Reader', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            "# Senior Laravel Developer\n\nTech Corp is hiring a Laravel developer with 5 years experience.",
            Response::HTTP_OK
        ),
    ]);

    $service = resolve(FetchJobPageService::class);

    $content = $service->fetch('https://example.com/jobs/123');

    expect($content)->toContain('Senior Laravel Developer');
});

test('fetches URLs containing special characters in the query string', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            '# Laravel Developer'.PHP_EOL.'We are hiring a Laravel developer.',
            Response::HTTP_OK
        ),
    ]);

    $service = resolve(FetchJobPageService::class);

    $content = $service->fetch('https://wuzzuf.net/jobs/p/abc?o=1&l=sp&t=sj&a=laravel|search-v3|hpb');

    expect($content)->toContain('Laravel Developer');
});

test('falls back to direct HTML fetch when Jina Reader fails', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
        'https://example.com/*' => Http::response(
            '<html><body><h1>Senior Laravel Developer</h1><p>We are hiring a Laravel developer.</p></body></html>',
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        ),
    ]);

    $service = resolve(FetchJobPageService::class);

    $content = $service->fetch('https://example.com/jobs/123');

    expect($content)->toContain('SENIOR LARAVEL DEVELOPER')
        ->and($content)->toContain('Laravel developer');
});

test('falls back to raw body when the page returns JSON', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
        'https://example.com/*' => Http::response(
            '{"title":"Laravel Developer","company":"Tech Corp"}',
            Response::HTTP_OK,
            ['Content-Type' => 'application/json']
        ),
    ]);

    $service = resolve(FetchJobPageService::class);

    $content = $service->fetch('https://example.com/api/jobs/123');

    expect($content)->toContain('"title":"Laravel Developer"');
});

test('throws when both readers fail to fetch the page', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
        'https://example.com/*' => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
    ]);

    $service = resolve(FetchJobPageService::class);

    $service->fetch('https://example.com/jobs/123');
})->throws(RuntimeException::class, 'Unable to fetch the job page.');

test('throws when the page content looks like a bot-check challenge', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response(
            "Title: Just a moment...\nURL Source: https://wuzzuf.net/jobs/p/123\nMarkdown Content:\nJust a moment...\n\nPerforming security verification...",
            Response::HTTP_OK
        ),
    ]);

    $service = resolve(FetchJobPageService::class);

    $service->fetch('https://wuzzuf.net/jobs/p/123');
})->throws(RuntimeException::class, 'The website is blocking automated access. Try pasting the job text instead.');

test('recovers when only the first attempt looks like a bot-check challenge', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::sequence()
            ->push(
                "Title: Just a moment...\nURL Source: https://example.com/jobs/123\nMarkdown Content:\nJust a moment...\n\nPerforming security verification...",
                Response::HTTP_OK
            )
            ->push('# Senior Laravel Developer'.PHP_EOL.'Tech Corp is hiring a Laravel developer.', Response::HTTP_OK),
    ]);

    $service = resolve(FetchJobPageService::class);

    $content = $service->fetch('https://example.com/jobs/123');

    expect($content)->toContain('Senior Laravel Developer');
});

test('recovers when only the first attempt returns too little readable content', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::sequence()
            ->push('Just a moment...', Response::HTTP_OK)
            ->push('# Laravel Developer'.PHP_EOL.'We are hiring a Laravel developer.', Response::HTTP_OK),
    ]);

    $service = resolve(FetchJobPageService::class);

    $content = $service->fetch('https://example.com/jobs/123');

    expect($content)->toContain('Laravel Developer');
});

test('throws when the page returns too little readable content', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response('A very short page.', Response::HTTP_OK),
    ]);

    $service = resolve(FetchJobPageService::class);

    $service->fetch('https://example.com/jobs/123');
})->throws(RuntimeException::class, 'The job page returned no readable content.');

test('falls back to direct fetch when Jina Reader throws an exception', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => fn () => throw new ConnectionException('boom'),
        'https://example.com/*' => Http::response(
            '<html><body><h1>Senior Laravel Developer</h1><p>We are hiring a Laravel developer.</p></body></html>',
            Response::HTTP_OK,
            ['Content-Type' => 'text/html']
        ),
    ]);

    $service = resolve(FetchJobPageService::class);

    $content = $service->fetch('https://example.com/jobs/123');

    expect($content)->toContain('Laravel developer');
});

test('throws when the direct fetch also throws an exception', function (): void {
    Http::fake([
        'https://r.jina.ai/*' => Http::response('', Response::HTTP_INTERNAL_SERVER_ERROR),
        'https://example.com/*' => fn () => throw new ConnectionException('boom'),
    ]);

    $service = resolve(FetchJobPageService::class);

    $service->fetch('https://example.com/jobs/123');
})->throws(RuntimeException::class, 'Unable to fetch the job page.');

test('rejects non-http URLs', function (): void {
    $service = resolve(FetchJobPageService::class);

    $service->fetch('ftp://example.com/jobs/123');
})->throws(RuntimeException::class, 'Job URL must be a valid http(s) link.');

test('rejects localhost URLs', function (): void {
    $service = resolve(FetchJobPageService::class);

    $service->fetch('http://localhost/jobs/123');
})->throws(RuntimeException::class, 'Job URL must point to a public address.');

test('rejects private IP URLs', function (): void {
    $service = resolve(FetchJobPageService::class);

    $service->fetch('https://127.0.0.1/jobs/123');
})->throws(RuntimeException::class, 'Job URL must point to a public address.');

test('rejects overly long URLs', function (): void {
    $service = resolve(FetchJobPageService::class);

    $service->fetch('https://example.com/'.str_repeat('a', 2100));
})->throws(RuntimeException::class, 'Job URL is too long.');
