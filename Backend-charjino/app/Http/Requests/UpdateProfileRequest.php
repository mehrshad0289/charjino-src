<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'نام نمایشی را وارد کنید.',
            'name.string' => 'نام نمایشی نامعتبر است.',
            'name.min' => 'نام نمایشی کوتاه است.',
            'name.max' => 'نام نمایشی طولانی است.',
        ];
    }
}
