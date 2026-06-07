<?php

namespace App\Rules;

use App\Support\Phone as PhoneSupport;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Phone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail('Поле :attribute должно быть строкой.');

            return;
        }

        if (! PhoneSupport::isValid($value)) {
            $fail('Поле :attribute должно содержать корректный российский номер телефона.');
        }
    }
}
