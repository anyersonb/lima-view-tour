<?php

namespace App\Http\Requests;

use App\Support\BookingCalendar;
use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tour_id' => ['required', 'integer', 'exists:tours,id'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
            'travel_date' => array_merge(['required'], BookingCalendar::dateRules()),
        ];
    }

    public function messages(): array
    {
        return [
            'tour_id.required' => __('cart.validation.tour_required'),
            'tour_id.exists' => __('cart.validation.tour_not_found'),
            'adults.required' => __('cart.validation.adults_required'),
            'adults.integer' => __('cart.validation.adults_integer'),
            'adults.min' => __('cart.validation.adults_min'),
            'adults.max' => __('cart.validation.max_quantity'),
            'children.required' => __('cart.validation.children_required'),
            'children.integer' => __('cart.validation.children_integer'),
            'children.min' => __('cart.validation.children_min'),
            'children.max' => __('cart.validation.max_quantity'),
            // Los de la fecha salen del mismo sitio que la regla, para que el
            // mensaje nombre el primer día realmente reservable.
            ...BookingCalendar::dateMessages(),
        ];
    }
}
