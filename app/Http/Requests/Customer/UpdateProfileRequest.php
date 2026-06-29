<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = auth('customer')->id();

        return [
            'name'             => ['required', 'string', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-\(\)]{7,20}$/'],
            'email'            => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customerId)],
            'password'         => ['nullable', 'string', 'min:8', 'confirmed'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
        ];
    }
}
