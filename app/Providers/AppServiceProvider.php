<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\FetchJobPageService;
use App\Services\GroqClient;
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
        /** @var string $apiKey */
        $apiKey = config('services.groq.api_key');
        /** @var string $apiChat */
        $apiChat = config('services.groq.api_chat');
        /** @var int $timeout */
        $timeout = config('ai_services.timeout');

        $this->app->singleton(GroqClient::class, fn (): GroqClient => new GroqClient(
            models: $models,
            temperature: $temperature,
            apiKey: $apiKey,
            apiChat: $apiChat,
            timeout: $timeout,
        ));

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
