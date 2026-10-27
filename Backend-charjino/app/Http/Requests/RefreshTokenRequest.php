<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefreshTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string', 'min:16'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'refresh_token.required' => 'توکن بازیابی موجود نیست',
            'refresh_token.min' => 'توکن بازیابی موجود نیست',
        ];
    }
}
