<?php

namespace App\Http\Requests\Customer;

use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone'    => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-\(\)]{7,20}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Run additional validation after the rule-set passes.
     * Verifies the reCAPTCHA token when the module is enabled.
     * No-op (returns immediately) when reCAPTCHA is disabled.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        /** @var RecaptchaService $recaptcha */
        $recaptcha = app(RecaptchaService::class);

        if (! $recaptcha->enabled()) {
            return;
        }

        $validator->after(function (\Illuminate\Validation\Validator $v) use ($recaptcha) {
            if (! $recaptcha->verify($this->input('recaptcha_token') ?: $this->input('g-recaptcha-response'), 'register', $this->ip())) {
                $v->errors()->add('recaptcha', __('recaptcha.failed'));
            }
        });
    }
}
