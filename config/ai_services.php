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

    'temperature' => (float) env('AI_TEMPERATURE', 0.3),

    'cover_letter_temperature' => (float) env('AI_COVER_LETTER_TEMPERATURE', 0.7),

    'timeout' => (int) env('AI_TIMEOUT', 60),
];
