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

    'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),

    'models' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'GEMINI_MODELS',
            // 'gemini-3.6-flash,gemini-3.5-flash-lite,gemini-3.1-flash-lite,gemini-2.5-flash,gemini-2.5-flash-lite'
        )),
    ), fn (string $model): bool => $model !== '')),

    'temperature' => (float) env('AI_TEMPERATURE', 0.3),

    'cover_letter_temperature' => (float) env('AI_COVER_LETTER_TEMPERATURE', 0.7),

    'timeout' => (int) env('AI_TIMEOUT', 60),
];
