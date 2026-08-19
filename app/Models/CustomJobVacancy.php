<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentType;
use Database\Factories\CustomJobVacancyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $category
 * @property string|null $company
 * @property string|null $title
 * @property string|null $description
 * @property string|null $job_text
 * @property string|null $job_url
 * @property string|null $responsibilities
 * @property string|null $requirements
 * @property string|null $skills_required
 * @property int|null $experience_years_min
 * @property int|null $experience_years_max
 * @property string|null $nice_to_have
 * @property string|null $location
 * @property string|null $expected_salary
 * @property EmploymentType $employment_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 */
final class CustomJobVacancy extends Model
{
    /** @use HasFactory<CustomJobVacancyFactory> */
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
     * @return HasMany<CustomJobApplication, $this>
     */
    public function customJobApplications(): HasMany
    {
        return $this->hasMany(CustomJobApplication::class);
    }

    protected function casts(): array
    {
        return [
            'title' => 'string',
            'category' => 'string',
            'company' => 'string',
            'user_id' => 'string',
            'description' => 'string',
            'job_text' => 'string',
            'job_url' => 'string',
            'location' => 'string',
            'expected_salary' => 'string',
            'employment_type' => EmploymentType::class,
            'responsibilities' => 'string',
            'requirements' => 'string',
            'skills_required' => 'string',
            'experience_years_min' => 'integer',
            'experience_years_max' => 'integer',
            'nice_to_have' => 'string',
        ];
    }
}
