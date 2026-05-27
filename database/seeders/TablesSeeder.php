<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TablesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['number' => 1, 'label' => 'У окна'],
            ['number' => 2, 'label' => null],
            ['number' => 3, 'label' => null],
            ['number' => 4, 'label' => 'У стены'],
            ['number' => 5, 'label' => null],
            ['number' => 6, 'label' => 'Барная стойка'],
        ];

        foreach ($rows as $row) {
            Table::updateOrCreate(
                ['number' => $row['number']],
                $row + ['qr_token' => Table::generateToken(), 'is_active' => true],
            );
        }
    }
}
