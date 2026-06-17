<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Status;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property string $id
 * @property string|null $username
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property Status $status
 * @property string|null $provider
 * @property string|null $provider_id
 * @property string|null $verification_code
 * @property Carbon|null $verification_code_expire_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $access_token
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read Profile|null $profile
 * @property-read Resume|null $resume
 * @property-read Collection<int, PersonalAccessToken> $tokens
 */
#[Hidden([
    'password',
    'remember_token',
    'updated_at',
    'deleted_at',
    'verification_code',
    'verification_code_expire_at',
])]
final class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    public function isActive(): bool
    {
        return $this->status === Status::ACTIVE;
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasOne<Resume, $this>
     */
    public function resume(): HasOne
    {
        return $this->hasOne(Resume::class);
    }

    /**
     * @return HasMany<CustomJobVacancy, $this>
     */
    public function customJobVacancies(): HasMany
    {
        return $this->hasMany(CustomJobVacancy::class);
    }

    /**
     * @return HasMany<CustomJobApplication, $this>
     */
    public function customJobApplications(): HasMany
    {
        return $this->hasMany(CustomJobApplication::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'provider' => 'string',
            'provider_id' => 'string',
            'status' => Status::class,
            'verification_code' => 'string',
            'verification_code_expire_at' => 'datetime',
        ];
    }
}
