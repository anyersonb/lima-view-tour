<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name'  => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'regex:/^(\+51\s?)?9\d{8}$/'],
            'travel_date'    => ['required', 'date', 'after:today'],
            'culqi_token'    => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_phone.regex'    => 'El teléfono debe ser peruano: +51 9XXXXXXXX o 9XXXXXXXX.',
            'travel_date.after'       => 'La fecha de viaje debe ser posterior a hoy.',
            'culqi_token.required'    => 'No se recibió el token de pago. Intente nuevamente.',
        ];
    }
}
