<?php

namespace Database\Factories;

use App\Enums\DeliveryType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = Customer::factory()->create();
        $deliveryType = fake()->randomElement(DeliveryType::cases());
        $isDelivery = $deliveryType === DeliveryType::Delivery;

        $subtotal = fake()->randomFloat(2, 300, 3000);
        $modifiersTotal = fake()->randomFloat(2, 0, 500);
        $deliveryFee = $isDelivery ? fake()->randomFloat(2, 100, 300) : 0;

        return [
            'number' => 'A-'.strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'status' => OrderStatus::New,
            'delivery_type' => $deliveryType,
            'payment_method' => fake()->randomElement([
                PaymentMethod::Cash,
                PaymentMethod::CardOnline,
                PaymentMethod::CardCourier,
            ]),
            'payment_status' => PaymentStatus::Pending,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'delivery_street' => $isDelivery ? fake()->streetAddress() : null,
            'delivery_apartment' => $isDelivery ? (string) fake()->numberBetween(1, 200) : null,
            'delivery_entrance' => $isDelivery ? (string) fake()->numberBetween(1, 8) : null,
            'delivery_floor' => $isDelivery ? (string) fake()->numberBetween(1, 20) : null,
            'subtotal' => $subtotal,
            'modifiers_total' => $modifiersTotal,
            'delivery_fee' => $deliveryFee,
            'discount_total' => 0,
            'total' => $subtotal + $modifiersTotal + $deliveryFee,
            'customer_comment' => fake()->optional()->sentence(),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function pos(): static
    {
        return $this->state(fn () => [
            'source' => OrderSource::Pos,
            'status' => OrderStatus::Accepted,
            'accepted_at' => now(),
            'customer_id' => null,
            'customer_name' => 'Гость',
            'customer_phone' => '',
            'payment_method' => PaymentMethod::Cash,
            'payment_status' => PaymentStatus::Paid,
            'delivery_type' => DeliveryType::Pickup,
            'delivery_fee' => 0,
            'delivery_street' => null,
            'delivery_apartment' => null,
            'delivery_entrance' => null,
            'delivery_floor' => null,
        ]);
    }
}
