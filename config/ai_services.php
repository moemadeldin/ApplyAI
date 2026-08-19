<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure settings for the AI services like model
    | selection and response creativity level (temperature).
    */

    'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),

    'models' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'AI_MODELS',
            'llama-3.3-70b-versatile,openai/gpt-oss-120b,openai/gpt-oss-20b,meta-llama/llama-4-scout-17b-16e-instruct,llama-3.1-8b-instant'
        )),
    ), fn (string $model): bool => $model !== '')),

    'temperature' => (float) env('AI_TEMPERATURE', 0.3),

    'cover_letter_temperature' => (float) env('AI_COVER_LETTER_TEMPERATURE', 0.7),

    'timeout' => (int) env('AI_TIMEOUT', 60),
];
