<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveTableCheckRequest extends FormRequest
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
            'table_id' => ['required', 'integer', 'exists:tables,id'],
        ];
    }
}
