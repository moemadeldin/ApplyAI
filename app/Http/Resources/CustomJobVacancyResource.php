<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomJobVacancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CustomJobVacancy $resource
 */
final class CustomJobVacancyResource extends JsonResource
{
    public const array JSON_STRUCTURE = [
        'id',
        'category',
        'company',
        'title',
        'description',
        'job_url',
        'location',
        'expected_salary',
        'employment_type',
        'responsibilities',
        'requirements',
        'skills_required',
        'experience_years_min',
        'experience_years_max',
        'nice_to_have',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'category' => $this->resource->category,
            'company' => $this->resource->company,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'job_url' => $this->resource->job_url,
            'location' => $this->resource->location,
            'expected_salary' => $this->resource->expected_salary,
            'employment_type' => $this->resource->employment_type->label(),
            'responsibilities' => $this->resource->responsibilities,
            'requirements' => $this->resource->requirements,
            'skills_required' => $this->resource->skills_required,
            'experience_years_min' => $this->resource->experience_years_min,
            'experience_years_max' => $this->resource->experience_years_max,
            'nice_to_have' => $this->resource->nice_to_have,
            'created_at' => $this->resource->created_at,
        ];
    }
}
