<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\FetchJobPageService;
use App\Services\GeminiClient;
use App\Services\OpenAiCompatibleClient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        Model::shouldBeStrict();

        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised());

        /** @var array<int, string> $models */
        $models = config('ai_services.models', []);
        /** @var float $temperature */
        $temperature = config('ai_services.temperature');

        $this->app->singleton(OpenAiCompatibleClient::class, function (): ?OpenAiCompatibleClient {
            if (! config('openrouter.enabled')) {
                return null;
            }

            /** @var array<int, string> $models */
            $models = config('openrouter.models', []);
            /** @var string $apiKey */
            $apiKey = config('openrouter.api_key');

            if ($models === [] || $apiKey === '' || $apiKey === null) {
                return null;
            }

            /** @var float $temperature */
            $temperature = config('ai_services.temperature');
            /** @var string $baseUrl */
            $baseUrl = config('openrouter.base_url');
            /** @var int $timeout */
            $timeout = config('openrouter.request_timeout');

            return new OpenAiCompatibleClient(
                models: $models,
                temperature: $temperature,
                apiKey: $apiKey,
                baseUrl: $baseUrl,
                timeout: $timeout,
            );
        });

        $this->app->singleton(GeminiClient::class, fn (): GeminiClient => new GeminiClient(
            models: $models,
            temperature: $temperature,
            fallbackClient: $this->app->make(OpenAiCompatibleClient::class),
        ));

        /** @var int $timeout */
        $timeout = config('ai_services.timeout');
        /** @var string $jinaApiKey */
        $jinaApiKey = config('services.jina.api_key');
        /** @var string $jinaReaderUrl */
        $jinaReaderUrl = config('services.jina.reader_url');

        $this->app->singleton(FetchJobPageService::class, fn (): FetchJobPageService => new FetchJobPageService(
            apiKey: $jinaApiKey,
            readerUrl: $jinaReaderUrl,
            timeout: $timeout,
        ));
    }
}
