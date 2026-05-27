<?php

namespace Database\Seeders;

use App\Enums\PromoDiscountType;
use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodesSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            [
                'code' => 'WELCOME10',
                'description' => 'Скидка 10% на первый заказ (макс. 300 ₽)',
                'discount_type' => PromoDiscountType::Percent,
                'value' => 10,
                'max_discount_amount' => 300,
                'min_order_amount' => 500,
                'first_order_only' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'FIX200',
                'description' => 'Минус 200 ₽ от заказа от 1000 ₽',
                'discount_type' => PromoDiscountType::Fixed,
                'value' => 200,
                'min_order_amount' => 1000,
                'first_order_only' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($codes as $data) {
            PromoCode::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
