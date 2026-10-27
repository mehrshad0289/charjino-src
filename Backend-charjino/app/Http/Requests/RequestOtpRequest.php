<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phoneNumber')) {
            $this->merge([
                'phoneNumber' => Phone::normalizeIranianMobile((string) $this->input('phoneNumber')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phoneNumber.required' => 'شماره همراه معتبر نیست.',
            'phoneNumber.string' => 'شماره همراه معتبر نیست.',
            'phoneNumber.regex' => 'شماره همراه معتبر نیست.',
        ];
    }
}
