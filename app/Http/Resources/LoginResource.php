<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read User $resource
 */
final class LoginResource extends JsonResource
{
    public const array JSON_STRUCTURE = [
        'user' => [
            'id',
            'email',
            'avatar',
        ],
        'access_token',
        'needs_resume',
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasResume = Cache::remember('user:has_resume:'.$this->resource->id, 300, fn () => Resume::query()
            ->where('user_id', $this->resource->id)
            ->exists()
        );

        return [
            'user' => [
                'id' => $this->resource->id,
                'email' => $this->resource->email,
                'avatar' => $this->resource->profile?->avatar !== null
                    ? Storage::disk('s3')->url($this->resource->profile->avatar)
                    : null,
            ],
            'access_token' => $this->resource->access_token,
            'needs_resume' => ! $hasResume,
        ];
    }
}
