<?php

namespace App\Actions\Promo;

use App\Enums\PromoDiscountType;
use App\Models\Customer;
use App\Models\PromoCode;
use Illuminate\Validation\ValidationException;

class ApplyPromoCode
{
    /**
     * Validate a promo code against the current cart context and return the
     * computed discount + the loaded PromoCode model.
     *
     * @return array{promo: PromoCode, discount: float}
     *
     * @throws ValidationException
     */
    public function handle(string $code, float $subtotal, ?string $customerPhone = null): array
    {
        $code = trim($code);
        if ($code === '') {
            $this->fail('Введите промокод.');
        }

        $promo = PromoCode::query()
            ->where('code', $code)
            ->first();

        if (! $promo) {
            $this->fail('Промокод не найден.');
        }

        if (! $promo->is_active) {
            $this->fail('Промокод отключён.');
        }

        $now = now();
        if ($promo->starts_at && $now->lt($promo->starts_at)) {
            $this->fail('Промокод ещё не активен.');
        }
        if ($promo->ends_at && $now->gt($promo->ends_at)) {
            $this->fail('Срок действия промокода истёк.');
        }

        if ((float) $promo->min_order_amount > 0 && $subtotal < (float) $promo->min_order_amount) {
            $this->fail("Промокод действует от {$this->formatRub((float) $promo->min_order_amount)}.");
        }

        if ($promo->max_uses_global !== null) {
            $globalUses = $promo->usages()->count();
            if ($globalUses >= $promo->max_uses_global) {
                $this->fail('Промокод уже использован максимальное число раз.');
            }
        }

        $customer = null;
        if ($customerPhone !== null && $customerPhone !== '') {
            $customer = Customer::query()->where('phone', $customerPhone)->first();
        }

        if ($promo->first_order_only) {
            if ($customer && $customer->orders()->exists()) {
                $this->fail('Промокод действует только для первого заказа.');
            }
        }

        if ($promo->max_uses_per_customer !== null && $customer) {
            $perCustomerUses = $promo->usages()->where('customer_id', $customer->id)->count();
            if ($perCustomerUses >= $promo->max_uses_per_customer) {
                $this->fail('Вы уже использовали этот промокод максимальное число раз.');
            }
        }

        $discount = $this->computeDiscount($promo, $subtotal);

        return ['promo' => $promo, 'discount' => $discount];
    }

    public function computeDiscount(PromoCode $promo, float $subtotal): float
    {
        $raw = match ($promo->discount_type) {
            PromoDiscountType::Percent => $subtotal * ((float) $promo->value) / 100,
            PromoDiscountType::Fixed => (float) $promo->value,
        };

        if ($promo->discount_type === PromoDiscountType::Percent && $promo->max_discount_amount !== null) {
            $raw = min($raw, (float) $promo->max_discount_amount);
        }

        return round(min($raw, $subtotal), 2);
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['promo_code' => $message]);
    }

    protected function formatRub(float $value): string
    {
        return number_format($value, 0, ',', ' ').' ₽';
    }
}
