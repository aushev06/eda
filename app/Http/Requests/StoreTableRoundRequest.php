<?php

namespace App\Http\Requests;

use App\Support\Settings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTableRoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tighter limits than the cashier's AddCheckItemsRequest, because this
     * route is public and fires straight to the kitchen.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.modifier_ids' => ['array'],
            'items.*.modifier_ids.*' => ['integer', 'exists:modifiers,id'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! Settings::isAcceptingOrders()) {
                    $validator->errors()->add('items', Settings::closedReason() ?? 'Сейчас мы не принимаем заказы.');
                }
            },
        ];
    }
}
