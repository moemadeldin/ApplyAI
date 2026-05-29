<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read Resume $resource
 */
final class ResumeResource extends JsonResource
{
    public const array JSON_STRUCTURE = [
        'id',
        'user_id',
        'name',
        'path',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'user_id' => $this->resource->user_id,
            'name' => $this->resource->name,
            'path' => $this->resolveFileUrl($this->resource->path),
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
