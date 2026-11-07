<?php

namespace Database\Factories;

use App\Enums\PrinterConnectionType;
use App\Enums\PrinterType;
use App\Models\Printer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Printer>
 */
class PrinterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => PrinterType::Receipt,
            'name' => 'Касса '.fake()->unique()->numberBetween(1, 9999),
            'connection_type' => PrinterConnectionType::Network,
            'address' => fake()->localIpv4(),
            'port' => 9100,
            'tape_width' => 80,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function bluetooth(): static
    {
        return $this->state(fn () => [
            'connection_type' => PrinterConnectionType::Bluetooth,
            'address' => fake()->macAddress(),
            'port' => null,
        ]);
    }
}
