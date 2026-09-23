<?php

declare(strict_types=1);

namespace App\Utilities;

final readonly class Constants
{
    public const string REGISTER_TOKEN_TYPE = 'Register';

    public const string LOGIN_TOKEN_TYPE = 'Login';

    public const string PASSWORD_RESET_TOKEN_TYPE = 'Reset';

    public const string EMAIL_VERIFICATION_TOKEN_TYPE = 'Verify';

    public const string SOCIAL_LOGIN_TOKEN_TYPE = 'SocialLogin';

    public const string REGISTER_TOKEN_NAME = 'Register Access Token';

    public const string LOGIN_TOKEN_NAME = 'Login Access Token';

    public const string PASSWORD_RESET_TOKEN_NAME = 'Password Reset Token';

    public const string EMAIL_VERIFICATION_TOKEN_NAME = 'Email Verification Token';

    public const string SOCIAL_LOGIN_TOKEN_NAME = 'Social Login Access Token';

    public const int NUMBER_OF_PAGINATED_JOB_VACANCIES = 6;

    public const int MIN_VERIFICATION_CODE = 100_000;

    public const int MAX_VERIFICATION_CODE = 999_999;

    public const int EXPIRATION_VERIFICATION_CODE_TIME_IN_MINUTES = 5;

    public const int NUMBER_OF_PAGINATED_JOB_APPLICATIONS = 10;

    public const int MINIMUM_SCORE = 70;

    public const int ESTIMATED_PROCESSING_TIME_SECONDS = 90;

    public const string DEFAULT_PROFILE_PICTURE_PATH = 'profile_pictures/default_pfp.png';

    public const string PROFILE_PICTURE_PATH = 'profile_pictures';

    public const string RESUMES_PATH = 'resumes';

    public const string AI_CACHE_GENERATION_KEY = 'ai:generation';

    public const string AI_CACHE_GEMINI_PREFIX = 'ai:gemini:';

    public const string AI_CACHE_FALLBACK_KEY = 'fallback';

    public const string AI_CACHE_OPENROUTER_PREFIX = 'ai:openrouter:';

    public const string AI_CACHE_DEEPSEEK_PREFIX = 'ai:deepseek:';

    public const int AI_CACHE_TTL_SECONDS = 86400 * 30;

    public const int AI_GEMINI_MAX_ATTEMPTS_PER_MODEL = 3;

    public const int AI_OPENROUTER_MAX_ATTEMPTS_PER_MODEL = 2;

    public const int AI_CIRCUIT_BREAKER_FAILURE_THRESHOLD = 3;

    public const int AI_CIRCUIT_BREAKER_COOLDOWN_SECONDS = 60;

    public const string AI_CIRCUIT_BREAKER_CACHE_PREFIX = 'ai:circuit:';

    public const string FETCH_JOB_USER_AGENT = 'ApplyAI/1.0 (job page fetcher)';

    public const int MAX_FETCH_ATTEMPTS = 2;

    public const int MAX_JOB_URL_LENGTH = 2048;

    public const int MIN_READABLE_CONTENT_LENGTH = 50;

    public const string SYSTEM_PROMPT_EVALUATION = 'You are a structured evaluator. Always return only valid JSON.';

    public const string SYSTEM_PROMPT_COVER_LETTER = 'You are a professional cover letter writer.';

    public const string SYSTEM_PROMPT_MOCK_INTERVIEW = 'You are a structured interviewer. Always return only valid JSON.';

    public const string SYSTEM_PROMPT_PARSE_JOB_VACANCY = 'You are a job vacancy parser.';

    public const string SYSTEM_PROMPT_OPTIMIZE_RESUME = 'You are an expert resume strategist and ATS optimization specialist.';
}
