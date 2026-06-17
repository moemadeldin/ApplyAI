<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class ChangePasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];

        if ($this->user()?->password !== null) {
            $rules['current_password'] = ['required', 'string'];
        }

        return $rules;
    }
}
