<?php

namespace App\Http\Requests;

use App\Enums\DeliveryType;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pos') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isDineIn = $this->input('delivery_type') === DeliveryType::DineIn->value;

        return [
            'delivery_type' => ['required', Rule::in([DeliveryType::Pickup->value, DeliveryType::DineIn->value])],
            'table_id' => [$isDineIn ? 'required' : 'nullable', 'integer', 'exists:tables,id'],
            'customer_comment' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.modifier_ids' => ['array'],
            'items.*.modifier_ids.*' => ['integer', 'exists:modifiers,id'],

            // Payment is taken before the order fires to the kitchen.
            'tenders' => ['required', 'array', 'min:1'],
            'tenders.*.method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::CardOnline->value])],
            'tenders.*.amount' => ['required', 'numeric', 'min:0.01'],
            'tenders.*.received_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'delivery_type' => 'тип заказа',
            'table_id' => 'стол',
            'items' => 'позиции',
        ];
    }
}
