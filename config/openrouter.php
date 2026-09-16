<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | OpenRouter Configuration
    |--------------------------------------------------------------------------
    |
    | OpenRouter is an OpenAI-compatible gateway to hundreds of models,
    | including rate-limited free models (IDs ending in ":free"). It is used
    | as the last-resort fallback whenever every Gemini model fails.
    |
    | Free-tier limits (no credits purchased): 20 requests per minute and
    | 50 requests per day. A one-time $10 credit purchase permanently raises
    | the daily limit to 1,000 requests. Free model availability rotates, so
    | review https://openrouter.ai/models and update OPENROUTER_MODELS as needed.
    */

    'enabled' => (bool) env('OPENROUTER_ENABLED', false),

    'api_key' => env('OPENROUTER_API_KEY'),

    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'models' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'OPENROUTER_MODELS',
            'nvidia/nemotron-3-ultra-550b-a55b:free,cohere/north-mini-code:free,google/gemma-4-31b-it:free,qwen/qwen3-coder:free,openrouter/free'
        )),
    ), fn (string $model): bool => $model !== '')),

    'request_timeout' => (int) env('OPENROUTER_REQUEST_TIMEOUT', 60),
];
