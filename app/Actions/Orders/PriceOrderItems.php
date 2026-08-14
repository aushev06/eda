<?php

namespace App\Actions\Orders;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PriceOrderItems
{
    /**
     * Validate a set of requested line items against live product/modifier
     * state (active, not in stop-list, allowed modifiers, group min/max) and
     * compute priced snapshot rows. Shared by CreateOrder (site + quick POS)
     * and AddItemsToCheck (table service rounds) so pricing stays identical.
     *
     * @param  array<int, array{product_id: int, quantity: int, modifier_ids?: array<int, int>}>  $items
     * @return array{0: float, 1: float, 2: array<int, array<string, mixed>>}
     */
    public function handle(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->unique()->all();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->where('in_stop_list', false)
            ->with('modifierGroups.modifiers')
            ->get()
            ->keyBy('id');

        $missing = collect($productIds)->diff($products->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Некоторые блюда недоступны для заказа.',
            ]);
        }

        return $this->price($items, $products);
    }

    /**
     * @param  array<int, array{product_id: int, quantity: int, modifier_ids?: array<int, int>}>  $items
     * @param  Collection<int, Product>  $products
     * @return array{0: float, 1: float, 2: array<int, array<string, mixed>>}
     */
    protected function price(array $items, Collection $products): array
    {
        $subtotal = 0.0;
        $modifiersTotalAll = 0.0;
        $rows = [];

        foreach ($items as $idx => $line) {
            /** @var Product $product */
            $product = $products[$line['product_id']];
            $quantity = max(1, (int) $line['quantity']);
            $unitPrice = (float) $product->price;

            $allowedModifierIds = $product->modifierGroups
                ->flatMap(fn ($g) => $g->modifiers)
                ->pluck('id')
                ->all();

            $modifierIds = array_values(array_unique(array_map('intval', $line['modifier_ids'] ?? [])));
            $unknown = array_diff($modifierIds, $allowedModifierIds);
            if (! empty($unknown)) {
                throw ValidationException::withMessages([
                    "items.$idx.modifier_ids" => 'Выбранные опции не относятся к этому блюду.',
                ]);
            }

            $perUnitDelta = 0.0;
            $modSnapshots = [];

            foreach ($product->modifierGroups as $group) {
                $selectedFromGroup = $group->modifiers->filter(
                    fn ($m) => in_array($m->id, $modifierIds, true)
                );

                $count = $selectedFromGroup->count();
                if ($count < $group->min_select || $count > $group->max_select) {
                    throw ValidationException::withMessages([
                        "items.$idx.modifier_ids" => "Группа «{$group->name}»: выберите от {$group->min_select} до {$group->max_select} вариантов.",
                    ]);
                }

                foreach ($selectedFromGroup as $modifier) {
                    $delta = (float) $modifier->price_delta;
                    $perUnitDelta += $delta;
                    $modSnapshots[] = [
                        'modifier_id' => $modifier->id,
                        'modifier_name' => $modifier->name,
                        'price_delta' => $delta,
                    ];
                }
            }

            $modifiersTotalLine = $perUnitDelta * $quantity;
            $lineTotal = $unitPrice * $quantity + $modifiersTotalLine;

            $subtotal += $unitPrice * $quantity;
            $modifiersTotalAll += $modifiersTotalLine;

            $rows[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'modifiers_total' => $modifiersTotalLine,
                'line_total' => $lineTotal,
                'modifiers' => $modSnapshots,
            ];
        }

        return [$subtotal, $modifiersTotalAll, $rows];
    }
}
