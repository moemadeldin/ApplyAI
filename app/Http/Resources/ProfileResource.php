<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read User $resource
 */
final class ProfileResource extends JsonResource
{
    public const array JSON_STRUCTURE = [
        'authenticated',
        'user' => [
            'id',
            'email',
            'avatar',
            'status',
            'resume_name',
            'resume',
        ],
    ];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'authenticated' => true,
            'user' => [
                'id' => $this->resource->id,
                'email' => $this->resource->email,
                'avatar' => Storage::disk('s3')->url($this->resource->profile?->avatar) ?? $this->resource->profile?->avatar,
                'status' => $this->resource->status->label(),
                'resume_name' => $this->resource->resume !== null ? $this->resource->resume->name : '',
                'resume' => Storage::disk('s3')->url($this->resource->resume->path) ?? $this->resource->resume->path,
            ],
        ];
    }
}
