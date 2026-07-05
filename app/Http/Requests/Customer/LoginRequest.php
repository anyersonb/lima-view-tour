<?php

namespace App\Http\Requests\Customer;

use App\Services\RecaptchaService;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
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
            if (! $recaptcha->verify($this->input('recaptcha_token') ?: $this->input('g-recaptcha-response'), 'login', $this->ip())) {
                $v->errors()->add('recaptcha', __('recaptcha.failed'));
            }
        });
    }
}
