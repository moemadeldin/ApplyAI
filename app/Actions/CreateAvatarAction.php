<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Utilities\Constants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final readonly class CreateAvatarAction
{
    public function handle(User $user, UploadedFile $avatar): User
    {
        $user->loadMissing('profile');

        return DB::transaction(function () use ($user, $avatar): User {
            if ($user->profile?->avatar && $user->profile->avatar !== Constants::DEFAULT_PROFILE_PICTURE_PATH) {
                Storage::disk('s3')->delete($user->profile->avatar);
            }

            $path = $avatar->storeAs(
                Constants::PROFILE_PICTURE_PATH.'/'.$user->id,
                Str::slug(pathinfo($avatar->getClientOriginalName(), PATHINFO_FILENAME))
                .'.'.$avatar->getClientOriginalExtension(),
                's3'
            );

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                ['avatar' => $path]
            );
            $user->load('profile');

            return $user;
        });
    }
}
