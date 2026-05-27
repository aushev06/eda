<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $sizeGroup = ModifierGroup::create([
            'name' => 'Размер',
            'min_select' => 1,
            'max_select' => 1,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        foreach (['25 см' => 0, '30 см' => 150, '35 см' => 300] as $name => $delta) {
            Modifier::create([
                'modifier_group_id' => $sizeGroup->id,
                'name' => $name,
                'price_delta' => $delta,
            ]);
        }

        $sauceGroup = ModifierGroup::create([
            'name' => 'Соус',
            'min_select' => 0,
            'max_select' => 3,
            'is_required' => false,
            'sort_order' => 2,
        ]);

        foreach (['Кетчуп', 'Майонез', 'Чесночный', 'Сырный'] as $name) {
            Modifier::create([
                'modifier_group_id' => $sauceGroup->id,
                'name' => $name,
                'price_delta' => 50,
            ]);
        }

        $catalog = [
            'Пицца' => [
                ['Маргарита', 590, [$sizeGroup, $sauceGroup]],
                ['Пепперони', 690, [$sizeGroup, $sauceGroup]],
                ['Четыре сыра', 750, [$sizeGroup]],
            ],
            'Бургеры' => [
                ['Чизбургер', 350, [$sauceGroup]],
                ['Двойной бургер', 490, [$sauceGroup]],
            ],
            'Напитки' => [
                ['Кола 0.5', 150, []],
                ['Сок апельсиновый', 180, []],
            ],
        ];

        $sort = 0;
        foreach ($catalog as $categoryName => $items) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'sort_order' => $sort++,
                'is_active' => true,
            ]);

            foreach ($items as $i => [$name, $price, $groups]) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => 'Описание блюда: '.$name,
                    'price' => $price,
                    'sort_order' => $i,
                ]);

                foreach ($groups as $j => $group) {
                    $product->modifierGroups()->attach($group->id, ['sort_order' => $j]);
                }
            }
        }
    }
}
