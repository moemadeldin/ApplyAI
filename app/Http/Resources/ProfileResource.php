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

    public function toArray(Request $request): array
    {
        return [
            'authenticated' => true,
            'user' => [
                'id' => $this->resource->id,
                'email' => $this->resource->email,
                'avatar' => $this->resolveFileUrl($this->resource->profile?->avatar),
                'status' => $this->resource->status->label(),
                'resume_name' => $this->resource->resume !== null ? $this->resource->resume->name : '',
                'resume' => $this->resolveFileUrl($this->resource->resume?->path),
            ],
        ];
    }

    private function resolveFileUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return config('filesystems.default') === 's3'
            ? Storage::disk('s3')->url($path)
            : Storage::disk('public')->url($path);
    }
}
