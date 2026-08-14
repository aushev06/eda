<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PosQuoteRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.modifier_ids' => ['array'],
            'items.*.modifier_ids.*' => ['integer', 'exists:modifiers,id'],
        ];
    }
}
