<?php

namespace Database\Factories;

use App\Models\Modifier;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemModifier>
 */
class OrderItemModifierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modifier = Modifier::factory()->create();

        return [
            'order_item_id' => OrderItem::factory(),
            'modifier_id' => $modifier->id,
            'modifier_name' => $modifier->name,
            'price_delta' => (float) $modifier->price_delta,
        ];
    }
}
