<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewJobUrlRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'job_url' => ['required', 'regex:/^https?:\/\/[^\s]+$/i', 'max:2048'],
        ];
    }
}
