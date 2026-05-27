<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\WorkingHour;
use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Settings::defaults() as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Settings::set('general.name', 'Кафе «Фидель»');
        Settings::set('general.phone', '+7 (495) 000-00-00');
        Settings::set('general.email', 'hi@fidele.test');
        Settings::set('general.address', 'Москва, ул. Примерная, 1');
        Settings::set('orders.min_order_amount', 500);
        Settings::set('orders.min_delivery_amount', 800);

        for ($day = 1; $day <= 7; $day++) {
            WorkingHour::updateOrCreate(
                ['day_of_week' => $day],
                [
                    'opens_at' => '10:00',
                    'closes_at' => '22:00',
                    'is_closed' => false,
                ],
            );
        }

        Settings::flush();
    }
}
