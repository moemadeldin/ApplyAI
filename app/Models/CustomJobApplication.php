<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessingStatus;
use Database\Factories\CustomJobApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $user_id
 * @property string $custom_job_vacancy_id
 * @property ProcessingStatus $status
 * @property string|null $current_step
 * @property string|null $error_message
 * @property int|null $compatibility_score
 * @property array{strengths: list<string>, weaknesses: list<string>}|null $feedback
 * @property list<string>|null $improvement_suggestions
 * @property string|null $cover_letter
 * @property string|null $optimized_resume
 * @property-read User $user
 * @property-read CustomJobVacancy $customJobVacancy
 * @property-read MockInterview|null $mockInterview
 */
final class CustomJobApplication extends Model
{
    /** @use HasFactory<CustomJobApplicationFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CustomJobVacancy, $this>
     */
    public function customJobVacancy(): BelongsTo
    {
        return $this->belongsTo(CustomJobVacancy::class, 'custom_job_vacancy_id');
    }

    /**
     * @return HasOne<MockInterview, $this>
     */
    public function mockInterview(): HasOne
    {
        return $this->hasOne(MockInterview::class, 'application_id');
    }

    /**
     * @param  Builder<CustomJobApplication>  $query
     */
    #[Scope]
    protected function filterStatus(Builder $query, ?string $status): void
    {
        if (! in_array($status, [null, '', '0'], true)) {
            $query->whereHas('mockInterview', function (Builder $q) use ($status): void {
                $q->where('status', $status);
            });
        }
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'string',
            'custom_job_vacancy_id' => 'string',
            'status' => ProcessingStatus::class,
            'current_step' => 'string',
            'error_message' => 'string',
            'compatibility_score' => 'integer',
            'feedback' => 'array',
            'improvement_suggestions' => 'array',
            'cover_letter' => 'string',
            'optimized_resume' => 'string',
        ];
    }
}
