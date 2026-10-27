<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => mb_strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'ایمیل و رمز عبور را وارد کنید',
            'email.email' => 'ایمیل معتبر نیست',
            'password.required' => 'ایمیل و رمز عبور را وارد کنید',
            'password.min' => 'ایمیل و رمز عبور را وارد کنید',
        ];
    }
}
