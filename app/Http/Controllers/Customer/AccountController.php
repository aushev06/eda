<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(): Response
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return Inertia::render('account/index', [
            'customer' => $customer->only(['id', 'name', 'phone', 'email']),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $customer->update($data);

        return redirect()->route('account.index')->with('status', 'Профиль сохранён.');
    }

    public function orders(): Response
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $orders = $customer->orders()
            ->with(['items', 'deliveryZone:id,name'])
            ->latest('id')
            ->get([
                'id', 'number', 'status', 'delivery_type', 'total',
                'delivery_zone_id', 'created_at', 'customer_id',
            ]);

        return Inertia::render('account/orders', [
            'orders' => $orders,
        ]);
    }

    public function addresses(): Response
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $addresses = $customer->addresses()->orderByDesc('is_default')->latest('id')->get();

        return Inertia::render('account/addresses', [
            'addresses' => $addresses,
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $data = $request->validate([
            'street' => ['required', 'string', 'max:255'],
            'apartment' => ['nullable', 'string', 'max:32'],
            'entrance' => ['nullable', 'string', 'max:32'],
            'floor' => ['nullable', 'string', 'max:32'],
            'intercom' => ['nullable', 'string', 'max:32'],
            'instructions' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
        ]);

        if (($data['is_default'] ?? false) === true) {
            $customer->addresses()->update(['is_default' => false]);
        }

        $customer->addresses()->create($data);

        return redirect()->route('account.addresses')->with('status', 'Адрес добавлен.');
    }

    public function destroyAddress(CustomerAddress $address): RedirectResponse
    {
        abort_unless($address->customer_id === auth('customer')->id(), 403);

        $address->delete();

        return redirect()->route('account.addresses')->with('status', 'Адрес удалён.');
    }

    public function bonuses(): Response
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();
        $account = LoyaltyAccount::forCustomer($customer);

        $current = $account->currentLevel();
        $next = $account->nextLevel();

        $progress = null;
        if ($next) {
            $current_threshold = (float) ($current?->min_lifetime_spend ?? 0);
            $span = (float) $next->min_lifetime_spend - $current_threshold;
            $progressed = (float) $account->lifetime_spent_on_orders - $current_threshold;
            $progress = [
                'percent' => $span > 0 ? round(max(0, min(100, $progressed / $span * 100)), 1) : 0,
                'remaining' => max(0, (float) $next->min_lifetime_spend - (float) $account->lifetime_spent_on_orders),
            ];
        }

        $transactions = $account->transactions()
            ->with('order:id,number')
            ->limit(30)
            ->get(['id', 'loyalty_account_id', 'order_id', 'type', 'amount', 'balance_after', 'note', 'created_at']);

        return Inertia::render('account/bonuses', [
            'account' => [
                'balance' => (float) $account->balance,
                'lifetime_earned' => (float) $account->lifetime_earned,
                'lifetime_spent_on_orders' => (float) $account->lifetime_spent_on_orders,
            ],
            'current_level' => $current ? [
                'name' => $current->name,
                'cashback_percent' => (float) $current->cashback_percent,
            ] : null,
            'next_level' => $next ? [
                'name' => $next->name,
                'cashback_percent' => (float) $next->cashback_percent,
                'min_lifetime_spend' => (float) $next->min_lifetime_spend,
            ] : null,
            'progress' => $progress,
            'all_levels' => LoyaltyLevel::query()->ordered()->get(['id', 'name', 'min_lifetime_spend', 'cashback_percent']),
            'transactions' => $transactions,
        ]);
    }
}
