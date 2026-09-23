<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCustomJobVacancyRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'job_url' => ['nullable', 'regex:/^https?:\/\/[^\s]+$/i', 'max:2048', 'required_without:job_text'],
            'job_text' => ['nullable', 'string', 'required_without:job_url'],
        ];
    }
}
