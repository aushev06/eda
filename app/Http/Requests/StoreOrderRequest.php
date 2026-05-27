<?php

namespace App\Http\Requests;

use App\Enums\DeliveryType;
use App\Enums\PaymentMethod;
use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! Settings::isAcceptingOrders()) {
            throw ValidationException::withMessages([
                'items' => 'Заведение сейчас не принимает заказы.'.
                    (Settings::closedReason() ? ' '.Settings::closedReason() : ''),
            ]);
        }

        // Pull table_id from the QR session if the client didn't include it.
        if (! $this->filled('table_id') && $this->session()->has('table_id')) {
            $this->merge(['table_id' => $this->session()->get('table_id')]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isDelivery = $this->input('delivery_type') === DeliveryType::Delivery->value;
        $isDineIn = $this->input('delivery_type') === DeliveryType::DineIn->value;

        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:32'],
            'delivery_type' => ['required', Rule::enum(DeliveryType::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'customer_comment' => ['nullable', 'string', 'max:1000'],
            'promo_code' => ['nullable', 'string', 'max:64'],
            'bonus_to_use' => ['nullable', 'numeric', 'min:0'],
            'table_id' => [$isDineIn ? 'required' : 'nullable', 'integer', 'exists:tables,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.modifier_ids' => ['array'],
            'items.*.modifier_ids.*' => ['integer', 'exists:modifiers,id'],

            'delivery' => [$isDelivery ? 'required' : 'nullable', 'array'],
            'delivery.zone_id' => [$isDelivery ? 'required' : 'nullable', 'integer', 'exists:delivery_zones,id'],
            'delivery.street' => [$isDelivery ? 'required' : 'nullable', 'string', 'max:255'],
            'delivery.apartment' => ['nullable', 'string', 'max:32'],
            'delivery.entrance' => ['nullable', 'string', 'max:32'],
            'delivery.floor' => ['nullable', 'string', 'max:32'],
            'delivery.intercom' => ['nullable', 'string', 'max:32'],
            'delivery.instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'имя',
            'customer_phone' => 'телефон',
            'delivery_type' => 'тип доставки',
            'payment_method' => 'способ оплаты',
            'items' => 'товары',
            'delivery.zone_id' => 'зона доставки',
            'delivery.street' => 'улица',
        ];
    }
}
