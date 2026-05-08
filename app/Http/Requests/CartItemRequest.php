<?php

namespace App\Http\Requests;

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
            'tour_id'     => ['required', 'integer', 'exists:tours,id'],
            'adults'      => ['required', 'integer', 'min:1', 'max:20'],
            'children'    => ['required', 'integer', 'min:0', 'max:20'],
            'travel_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'tour_id.required'          => __('cart.validation.tour_required'),
            'tour_id.exists'            => __('cart.validation.tour_not_found'),
            'adults.required'           => __('cart.validation.adults_required'),
            'adults.integer'            => __('cart.validation.adults_integer'),
            'adults.min'                => __('cart.validation.adults_min'),
            'adults.max'                => __('cart.validation.max_quantity'),
            'children.required'         => __('cart.validation.children_required'),
            'children.integer'          => __('cart.validation.children_integer'),
            'children.min'              => __('cart.validation.children_min'),
            'children.max'              => __('cart.validation.max_quantity'),
            'travel_date.required'      => __('cart.validation.date_required'),
            'travel_date.date'          => __('cart.validation.date_invalid'),
            'travel_date.after_or_equal' => __('cart.validation.date_future'),
        ];
    }
}
