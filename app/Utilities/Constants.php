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

    public const string DEFAULT_PROFILE_PICTURE_PATH = 'profile_pictures/default_pfp.png';

    public const string PROFILE_PICTURE_PATH = 'profile_pictures';

    public const string RESUMES_PATH = 'resumes';
}
