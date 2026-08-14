<?php

namespace App\Actions\Pos;

use App\Actions\Orders\AdvanceOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PosTicketsUpdated;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettleOrder
{
    /** Payment methods a POS tender may use. Split is a summary marker, not a tender. */
    protected const ALLOWED_METHODS = [PaymentMethod::Cash, PaymentMethod::CardOnline];

    public function __construct(
        protected AdvanceOrderStatus $advance,
    ) {}

    /**
     * Settle an unpaid order with one or more tenders (split payment). Each
     * tender is {method, amount, received_amount?}. Records the breakdown in
     * order_payments, marks the order paid, and optionally advances its status
     * (Delivered for a closed table). Money is compared in integer kopecks so
     * decimal-string totals and JSON floats agree exactly.
     *
     * @param  array<int, array{method: string, amount: int|float, received_amount?: int|float|null}>  $tenders
     */
    public function handle(Order $order, array $tenders, User $cashier, ?OrderStatus $advanceTo = null): Order
    {
        return DB::transaction(function () use ($order, $tenders, $cashier, $advanceTo) {
            // Lock the row and re-check inside the transaction: a double-tap or
            // a second cashier must not both pass the unpaid guard.
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked->payment_status === PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'order' => 'Заказ уже оплачен.',
                ]);
            }

            $locked->loadMissing('items');
            $liveItems = $locked->items->filter(fn ($item) => $item->voided_at === null);
            if ($liveItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'Нельзя оплатить пустой заказ.',
                ]);
            }

            $rows = $this->validateTenders($tenders, $locked);

            $locked->payments()->delete();
            foreach ($rows as $row) {
                $locked->payments()->create($row);
            }

            $methods = array_values(array_unique(array_map(fn ($r) => $r['method'], $rows)));
            $locked->payment_method = count($methods) > 1
                ? PaymentMethod::Split
                : PaymentMethod::from($methods[0]);
            $locked->payment_status = PaymentStatus::Paid;
            $locked->paid_at = now();
            $locked->save();

            if ($advanceTo !== null) {
                $this->advance->handle($locked, $advanceTo, $cashier);
            }

            PosTicketsUpdated::dispatch($locked->id);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<int, array{method: string, amount: int|float, received_amount?: int|float|null}>  $tenders
     * @return array<int, array{method: string, amount: float, received_amount: float}>
     */
    protected function validateTenders(array $tenders, Order $order): array
    {
        if (empty($tenders)) {
            throw ValidationException::withMessages(['tenders' => 'Укажите хотя бы один способ оплаты.']);
        }

        $allowedValues = array_map(fn (PaymentMethod $m) => $m->value, self::ALLOWED_METHODS);
        $totalCents = $this->toCents($order->total);
        $sumCents = 0;
        $rows = [];

        foreach ($tenders as $idx => $tender) {
            $method = $tender['method'] ?? null;
            if (! in_array($method, $allowedValues, true)) {
                throw ValidationException::withMessages([
                    "tenders.$idx.method" => 'Недопустимый способ оплаты.',
                ]);
            }

            $amountCents = $this->toCents($tender['amount'] ?? 0);
            if ($amountCents <= 0) {
                throw ValidationException::withMessages([
                    "tenders.$idx.amount" => 'Сумма должна быть больше нуля.',
                ]);
            }

            // No single method can be charged more than the whole bill.
            if ($amountCents > $totalCents) {
                throw ValidationException::withMessages([
                    "tenders.$idx.amount" => 'Сумма по способу больше итога заказа.',
                ]);
            }

            $receivedCents = isset($tender['received_amount']) && $tender['received_amount'] !== null
                ? $this->toCents($tender['received_amount'])
                : $amountCents;

            // Only cash can exceed its applied amount (produces change); card is exact.
            if ($method === PaymentMethod::Cash->value) {
                if ($receivedCents < $amountCents) {
                    throw ValidationException::withMessages([
                        "tenders.$idx.received_amount" => 'Получено меньше суммы по способу.',
                    ]);
                }
            } else {
                $receivedCents = $amountCents;
            }

            $sumCents += $amountCents;
            $rows[] = [
                'method' => $method,
                'amount' => $amountCents / 100,
                'received_amount' => $receivedCents / 100,
            ];
        }

        if ($sumCents !== $totalCents) {
            throw ValidationException::withMessages([
                'tenders' => 'Сумма оплат не совпадает с итогом заказа.',
            ]);
        }

        return $rows;
    }

    protected function toCents(int|float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
