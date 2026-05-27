<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

class DeliveryZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'Центр',
                'color' => '#10b981',
                'polygon' => [
                    [55.7400, 37.5900],
                    [55.7400, 37.6500],
                    [55.7700, 37.6500],
                    [55.7700, 37.5900],
                ],
                'delivery_fee' => 150,
                'min_order_amount' => 800,
                'estimated_minutes_min' => 30,
                'estimated_minutes_max' => 50,
                'sort_order' => 1,
            ],
            [
                'name' => 'Ближнее кольцо',
                'color' => '#3b82f6',
                'polygon' => [
                    [55.7100, 37.5500],
                    [55.7100, 37.6900],
                    [55.8000, 37.6900],
                    [55.8000, 37.5500],
                ],
                'delivery_fee' => 250,
                'min_order_amount' => 1000,
                'estimated_minutes_min' => 45,
                'estimated_minutes_max' => 75,
                'sort_order' => 2,
            ],
            [
                'name' => 'Дальнее кольцо',
                'color' => '#f59e0b',
                'polygon' => [
                    [55.6700, 37.5000],
                    [55.6700, 37.7500],
                    [55.8400, 37.7500],
                    [55.8400, 37.5000],
                ],
                'delivery_fee' => 400,
                'min_order_amount' => 1500,
                'estimated_minutes_min' => 60,
                'estimated_minutes_max' => 100,
                'sort_order' => 3,
            ],
        ];

        foreach ($zones as $data) {
            DeliveryZone::updateOrCreate(
                ['name' => $data['name']],
                $data + ['is_active' => true],
            );
        }
    }
}
