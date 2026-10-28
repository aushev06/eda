<?php

namespace App\Actions\Pos;

use App\Models\Category;
use Illuminate\Support\Collection;

class BuildPosCatalog
{
    /**
     * Sellable menu for the cashier screen and the check editor: active
     * categories with in-stock products and their active modifiers.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'products' => fn ($q) => $q
                    ->where('is_active', true)
                    ->where('in_stop_list', false)
                    ->orderBy('sort_order'),
                'products.category',
                'products.modifierGroups' => fn ($q) => $q->orderBy('sort_order'),
                'products.modifierGroups.modifiers' => fn ($q) => $q
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'products' => $category->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'station' => $product->resolvedStation()->value,
                    'modifier_groups' => $product->modifierGroups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'min_select' => $group->min_select,
                        'max_select' => $group->max_select,
                        'modifiers' => $group->modifiers->map(fn ($modifier) => [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                            'price_delta' => (float) $modifier->price_delta,
                        ]),
                    ]),
                ]),
            ]);
    }
}
