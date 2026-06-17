<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ResolvesFileUrls
{
    protected function resolveFileUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return config('filesystems.default') === 's3'
            ? Storage::disk('s3')->url($path)
            : Storage::disk('public')->url($path);
    }
}
