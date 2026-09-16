<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | DeepSeek Configuration (Dormant)
    |--------------------------------------------------------------------------
    |
    | DeepSeek is an OpenAI-compatible provider. It is currently disabled and
    | not wired into the AI fallback chain: requests fail with "Insufficient
    | Balance" until the account is topped up. The active fallback provider is
    | OpenRouter (see config/openrouter.php). This config is kept so DeepSeek
    | can be re-enabled quickly if the account is funded.
    */

    'enabled' => (bool) env('DEEPSEEK_ENABLED', false),

    'api_key' => env('DEEPSEEK_API_KEY'),

    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),

    'models' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('DEEPSEEK_MODELS', 'deepseek-v4-flash,deepseek-v4-pro')),
    ), fn (string $model): bool => $model !== '')),

    'request_timeout' => (int) env('DEEPSEEK_REQUEST_TIMEOUT', 60),
];
