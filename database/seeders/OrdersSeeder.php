<?php

namespace Database\Seeders;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::with('modifierGroups.modifiers')->get();

        if ($products->isEmpty()) {
            $this->command->warn('Нет товаров — сначала выполни MenuSeeder.');

            return;
        }

        $statuses = [
            OrderStatus::New,
            OrderStatus::New,
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::Delivering,
            OrderStatus::Delivered,
            OrderStatus::Cancelled,
        ];

        foreach ($statuses as $index => $status) {
            $customer = Customer::factory()->create();
            $deliveryType = $index % 2 === 0 ? DeliveryType::Delivery : DeliveryType::Pickup;

            $order = Order::create([
                'number' => 'A-'.strtoupper(Str::random(6)),
                'customer_id' => $customer->id,
                'status' => $status,
                'delivery_type' => $deliveryType,
                'payment_method' => PaymentMethod::Cash,
                'payment_status' => $status === OrderStatus::Delivered ? PaymentStatus::Paid : PaymentStatus::Pending,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'delivery_street' => $deliveryType === DeliveryType::Delivery ? fake()->streetAddress() : null,
                'delivery_apartment' => $deliveryType === DeliveryType::Delivery ? (string) fake()->numberBetween(1, 200) : null,
                'subtotal' => 0,
                'modifiers_total' => 0,
                'delivery_fee' => $deliveryType === DeliveryType::Delivery ? 200 : 0,
                'discount_total' => 0,
                'total' => 0,
                'customer_comment' => fake()->optional()->sentence(),
                'accepted_at' => in_array($status, [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivering, OrderStatus::Delivered], true) ? now()->subMinutes(30) : null,
                'ready_at' => in_array($status, [OrderStatus::Ready, OrderStatus::Delivering, OrderStatus::Delivered], true) ? now()->subMinutes(10) : null,
                'delivered_at' => $status === OrderStatus::Delivered ? now()->subMinutes(2) : null,
                'cancelled_at' => $status === OrderStatus::Cancelled ? now()->subMinutes(5) : null,
                'cancellation_reason' => $status === OrderStatus::Cancelled ? 'Клиент не отвечает' : null,
            ]);

            $itemsCount = fake()->numberBetween(1, 3);
            $selectedProducts = $products->random(min($itemsCount, $products->count()));

            foreach ($selectedProducts as $product) {
                $quantity = fake()->numberBetween(1, 2);
                $unitPrice = (float) $product->price;

                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'modifiers_total' => 0,
                    'line_total' => $unitPrice * $quantity,
                ]);

                $modifiersTotal = 0;

                foreach ($product->modifierGroups as $group) {
                    $modifier = $group->modifiers->random();
                    OrderItemModifier::create([
                        'order_item_id' => $item->id,
                        'modifier_id' => $modifier->id,
                        'modifier_name' => $modifier->name,
                        'price_delta' => (float) $modifier->price_delta,
                    ]);
                    $modifiersTotal += (float) $modifier->price_delta * $quantity;
                }

                $item->update([
                    'modifiers_total' => $modifiersTotal,
                    'line_total' => $unitPrice * $quantity + $modifiersTotal,
                ]);
            }

            $order->loadMissing('items');
            $order->recalculateTotals();
            $order->save();

            $order->statusEvents()->create([
                'from_status' => null,
                'to_status' => $status,
                'created_at' => $order->created_at,
            ]);
        }
    }
}
