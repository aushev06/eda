<?php

namespace App\Http\Controllers;

use App\Actions\Promo\ApplyPromoCode;
use App\Rules\Phone as PhoneRule;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PromoController extends Controller
{
    public function validateCode(Request $request, ApplyPromoCode $action): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'customer_phone' => ['nullable', 'string', new PhoneRule],
        ]);

        try {
            $applied = $action->handle(
                code: $data['code'],
                subtotal: (float) $data['subtotal'],
                customerPhone: isset($data['customer_phone']) ? Phone::normalize($data['customer_phone']) : null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'valid' => false,
                'message' => $e->validator->errors()->first('promo_code') ?? 'Промокод недействителен.',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'code' => $applied['promo']->code,
            'description' => $applied['promo']->description,
            'discount' => $applied['discount'],
        ]);
    }
}
