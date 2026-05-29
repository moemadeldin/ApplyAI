<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\ExtractResumeTextJob;
use App\Models\Resume;
use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class CreateResumeAction
{
    /**
     * @param  array{path: string|UploadedFile}  $data
     */
    public function handle(array $data, User $user): Resume
    {
        $user->loadMissing('resume');

        $file = $data['path'];
        $isFileString = is_string($file);

        $resume = DB::transaction(function () use ($user, $file, $isFileString): Resume {
            if ($user->resume) {
                /** @var string $path */
                $path = $user->resume->path;
                Storage::disk('s3')->delete($path);
            }

            $filePath = $isFileString
                ? $file
                : $file->storeAs(
                    Constants::RESUMES_PATH.'/'.$user->id,
                    Str::slug(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME))
.'.'.$file->getClientOriginalExtension(),
                    's3'
                );

            return Resume::query()->updateOrCreate([
                'user_id' => $user->id,
            ],
                [
                    'name' => $isFileString
                        ? basename($file)
                        : $file->getClientOriginalName(),
                    'path' => $filePath,
                ]);
        });

        Cache::increment('ai:generation');
        Cache::forget('user:has_resume:'.$user->id);
        Cache::forget('user:profile:'.$user->id);

        dispatch_sync(new ExtractResumeTextJob($resume));

        return $resume;
    }
}
