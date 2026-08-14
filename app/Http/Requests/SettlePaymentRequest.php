<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettlePaymentRequest extends FormRequest
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
        return [
            'tenders' => ['required', 'array', 'min:1'],
            'tenders.*.method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::CardOnline->value])],
            'tenders.*.amount' => ['required', 'numeric', 'min:0.01'],
            'tenders.*.received_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
