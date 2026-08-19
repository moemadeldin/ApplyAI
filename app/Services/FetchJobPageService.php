<?php

declare(strict_types=1);

namespace App\Services;

use Html2Text\Html2Text;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final readonly class FetchJobPageService
{
    private const string USER_AGENT = 'ApplyAI/1.0 (job page fetcher)';

    private const int MAX_FETCH_ATTEMPTS = 2;

    public function __construct(
        private string $apiKey,
        private string $readerUrl,
        private int $timeout,
    ) {}

    public function fetch(string $url): string
    {
        $this->assertSafeUrl($url);

        $attempt = 1;

        while (true) {
            try {
                return $this->attemptFetch($url);
            } catch (RuntimeException $e) {
                throw_if($attempt >= self::MAX_FETCH_ATTEMPTS, $e);

                $attempt++;
            }
        }
    }

    private function attemptFetch(string $url): string
    {
        $content = $this->fetchViaReader($url);

        if ($content === '') {
            $content = $this->fetchDirectly($url);
        }

        $content = $this->normalize($content);

        throw_if($content === '', RuntimeException::class, 'Unable to fetch the job page.');

        $this->assertReadable($content);

        return $content;
    }

    private function assertReadable(string $content): void
    {
        $lower = mb_strtolower($content);

        $blockedPatterns = [
            'just a moment',
            'security verification',
            'verify you are a human',
            'checking your browser',
            'performance and security by cloudflare',
            'attention required',
            'ddos protection',
            'access denied',
            'enable javascript',
            'are you a robot',
        ];

        foreach ($blockedPatterns as $pattern) {
            throw_if(
                str_contains($lower, $pattern),
                RuntimeException::class,
                'The website is blocking automated access. Try pasting the job text instead.'
            );
        }

        throw_if(
            mb_strlen($content) < 50,
            RuntimeException::class,
            'The job page returned no readable content.'
        );
    }

    private function fetchViaReader(string $url): string
    {
        try {
            $http = Http::accept('text/plain')
                ->timeout($this->timeout);

            if ($this->apiKey !== '') {
                $http = $http->withToken($this->apiKey);
            }

            $response = $http->get($this->readerUrl.$url);

            return $response->successful() ? (string) $response->body() : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function fetchDirectly(string $url): string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout($this->timeout)
                ->get($url);

            if (! $response->successful()) {
                return '';
            }

            $contentType = mb_strtolower((string) ($response->header('Content-Type') ?? ''));

            if (str_contains($contentType, 'json')) {
                return (string) $response->body();
            }

            $html2text = new Html2Text;
            $html2text->set_html((string) $response->body());

            $text = $html2text->get_text();

            return is_string($text) ? $text : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function normalize(string $content): string
    {
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        return mb_trim($content);
    }

    private function assertSafeUrl(string $url): void
    {
        throw_if(mb_strlen($url) > 2048, RuntimeException::class, 'Job URL is too long.');

        $parts = parse_url($url);

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        throw_if(
            ! in_array($scheme, ['http', 'https'], true) || $host === null,
            RuntimeException::class,
            'Job URL must be a valid http(s) link.'
        );

        $hostname = mb_strtolower($host);

        throw_if(
            $hostname === 'localhost'
                || str_ends_with($hostname, '.localhost')
                || str_ends_with($hostname, '.local')
                || str_ends_with($hostname, '.internal'),
            RuntimeException::class,
            'Job URL must point to a public address.'
        );

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw_if(
                filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false,
                RuntimeException::class,
                'Job URL must point to a public address.'
            );
        }
    }
}
