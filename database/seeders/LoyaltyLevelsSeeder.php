<?php

namespace Database\Seeders;

use App\Models\LoyaltyLevel;
use Illuminate\Database\Seeder;

class LoyaltyLevelsSeeder extends Seeder
{
    public function run(): void
    {
        // Levels from BONUS.MD.
        $levels = [
            ['name' => 'Стартовый', 'min_lifetime_spend' => 0, 'cashback_percent' => 10, 'sort_order' => 1],
            ['name' => 'Основной', 'min_lifetime_spend' => 30000, 'cashback_percent' => 15, 'sort_order' => 2],
            ['name' => 'Премиальный', 'min_lifetime_spend' => 50000, 'cashback_percent' => 20, 'sort_order' => 3],
            ['name' => 'VIP', 'min_lifetime_spend' => 70000, 'cashback_percent' => 25, 'sort_order' => 4],
        ];

        foreach ($levels as $level) {
            LoyaltyLevel::updateOrCreate(['name' => $level['name']], $level);
        }
    }
}
