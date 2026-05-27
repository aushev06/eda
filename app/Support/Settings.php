<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    public const CACHE_KEY = 'settings.all';

    /**
     * @var array<string, mixed>|null
     */
    protected static ?array $defaults = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = static::all();

        return $values[$key] ?? $default ?? static::defaults()[$key] ?? null;
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        static::flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        static::flush();
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()->pluck('value', 'key')->toArray();
        });
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isAcceptingOrders(): bool
    {
        return (bool) static::get('mode.accepting_orders', true);
    }

    public static function closedReason(): ?string
    {
        return static::get('mode.closed_reason');
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return self::$defaults ??= [
            'general.name' => 'Моё заведение',
            'general.phone' => '',
            'general.email' => '',
            'general.address' => '',
            'mode.accepting_orders' => true,
            'mode.closed_reason' => null,
            'orders.min_order_amount' => 0,
            'orders.min_delivery_amount' => 0,
            'orders.base_prep_minutes' => 30,
            'orders.delivery_radius_km' => 10,
            'social.telegram' => '',
            'social.vk' => '',
            'social.instagram' => '',
            'loyalty.enabled' => true,
            'loyalty.max_spend_percent_per_order' => 30,
        ];
    }
}
