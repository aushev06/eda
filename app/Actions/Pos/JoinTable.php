<?php

namespace App\Actions\Pos;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Table;
use App\Models\TableGuest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class JoinTable
{
    /** Wrong PINs allowed per check within the decay window, across all devices. */
    public const MAX_PIN_ATTEMPTS = 20;

    public const PIN_ATTEMPT_DECAY_SECONDS = 600;

    /**
     * Add a guest to the table's open check after checking the waiter's PIN.
     * The per-check limit complements the per-IP route throttle, so rotating
     * addresses can't brute-force a 4-digit PIN during a visit. Signed-in
     * customers reuse their guest row, so a second device isn't a second person.
     */
    public function handle(Table $table, string $pin, string $name, ?Customer $customer = null): TableGuest
    {
        $order = Order::query()->openTableCheck($table->id)->whereNotNull('guest_pin')->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'pin' => 'Официант ещё не открыл стол для заказов.',
            ]);
        }

        $attemptsKey = "table-pin:{$order->id}";

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_PIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'pin' => 'Слишком много попыток. Обратитесь к официанту.',
            ]);
        }

        if (! hash_equals($order->guest_pin, $pin)) {
            RateLimiter::hit($attemptsKey, self::PIN_ATTEMPT_DECAY_SECONDS);

            throw ValidationException::withMessages([
                'pin' => 'Неверный код. Уточните его у официанта.',
            ]);
        }

        if (! $customer) {
            return $order->guests()->create(['name' => $name]);
        }

        $guest = $order->guests()->firstOrNew(['customer_id' => $customer->id]);
        $guest->fill(['name' => $name, 'revoked_at' => null])->save();

        return $guest;
    }
}
