<?php

namespace App\Http\Requests;

use App\Support\BookingCalendar;
use App\Support\TourLanguage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize phone before validation: strip all spaces so both
     * "+51 999 888 777" and "+51999888777" and "999888777" are accepted.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('customer_phone')) {
            $this->merge([
                'customer_phone' => preg_replace('/\s+/', '', $this->input('customer_phone')),
            ]);
        }

        // Cart cards used to post labels ("English", "Español"); the contract is
        // the code. Unknown text becomes null (=> the site language), never an error.
        if ($this->has('tour_language')) {
            $this->merge(['tour_language' => is_string($this->input('tour_language')) ? TourLanguage::normalize($this->input('tour_language')) : $this->input('tour_language')]);
        }
    }

    public function rules(): array
    {
        return [
            // payment_timing controls whether the charge is processed now or deferred
            'payment_timing' => ['required', 'string', 'in:now,later'],

            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            // Acepta número internacional con prefijo de país (+51, +1, etc.) tras quitar espacios
            'customer_phone' => ['required', 'string', 'regex:/^\+?\d{7,15}$/'],
            // Form field only: format and horizon. The lead-time rule depends on
            // each tour's departure time, so it is checked per cart ITEM in
            // CheckoutController::assertBookableDates().
            'travel_date' => array_merge(['required'], BookingCalendar::formDateRules()),

            // Pickup information (optional but captured when provided)
            'pickup_point' => ['nullable', 'string', 'max:100'],
            'pickup_detail' => ['nullable', 'string', 'max:255'],

            // B3: notas del cliente sobre su reserva — `bookings.notes` es
            // `text` desde la migración original, así que se persiste igual
            // que pickup_point/pickup_detail (ver processPayment()).
            'notes' => ['nullable', 'string', 'max:1000'],

            // Idioma del tour: código es|en|pt (las etiquetas se normalizan en
            // prepareForValidation). Se guarda en bookings.tour_language
            // (migración 2026_10_12_000001, tolerante si aún no corrió).
            'tour_language' => ['nullable', 'string', Rule::in(TourLanguage::CODES)],

            // B4: el checkbox de términos y condiciones. Los checkboxes NO
            // marcados no viajan en un submit HTML nativo, así que 'required'
            // alcanza para detectar tanto "no vino" como "vino vacío"; con
            // 'accepted' además solo se admite un valor "verdadero" (1, "on",
            // "yes", true), no cualquier string no vacío.
            'accept_terms' => ['required', 'accepted'],

            // Only required when the customer is paying now with a card
            'culqi_token' => ['nullable', 'string', 'required_if:payment_timing,now'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_timing.required' => 'Debe indicar si pagará ahora o después.',
            'payment_timing.in' => 'Opción de pago no válida.',
            'customer_phone.regex' => __('checkout_paypal.phone_invalid'),
            'customer_phone.required' => __('checkout_paypal.phone_invalid'),
            'accept_terms.required' => __('checkout_paypal.accept_terms_required'),
            'accept_terms.accepted' => __('checkout_paypal.accept_terms_required'),
            'culqi_token.required_if' => 'No se recibió el token de pago. Intente nuevamente.',
            ...BookingCalendar::dateMessages(),
        ];
    }
}
