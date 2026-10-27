<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merged = [];
        if ($this->has('phoneNumber')) {
            $merged['phoneNumber'] = Phone::normalizeIranianMobile((string) $this->input('phoneNumber'));
        }
        if ($this->has('code')) {
            $merged['code'] = Phone::onlyDigits((string) $this->input('code'));
        }
        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phoneNumber' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'code' => ['required', 'string', 'min:4', 'max:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phoneNumber.required' => 'شماره همراه معتبر نیست.',
            'phoneNumber.regex' => 'شماره همراه معتبر نیست.',
            'code.required' => 'کد تأیید معتبر نیست.',
            'code.min' => 'کد تأیید معتبر نیست.',
            'code.max' => 'کد تأیید معتبر نیست.',
        ];
    }
}
