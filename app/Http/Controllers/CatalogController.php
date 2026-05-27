<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyLevel;
use App\Models\StoryGroup;
use App\Support\Settings;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'products' => fn ($q) => $q
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
                'products.modifierGroups' => fn ($q) => $q->orderBy('sort_order'),
                'products.modifierGroups.modifiers' => fn ($q) => $q
                    ->where('is_active', true)
                    ->orderBy('sort_order'),
            ])
            ->get();

        $storyGroups = StoryGroup::query()
            ->active()
            ->ordered()
            ->with([
                'stories' => fn ($q) => $q->active()->ordered(),
            ])
            ->get()
            ->filter(fn (StoryGroup $group) => $group->stories->isNotEmpty())
            ->values();

        return Inertia::render('catalog/index', [
            'categories' => $categories,
            'story_groups' => $storyGroups,
            'establishment' => [
                'name' => Settings::get('general.name'),
                'phone' => Settings::get('general.phone'),
                'accepting_orders' => Settings::isAcceptingOrders(),
                'closed_reason' => Settings::closedReason(),
                'min_order_amount' => (float) Settings::get('orders.min_order_amount', 0),
            ],
        ]);
    }

    public function checkout(): Response
    {
        $zones = DeliveryZone::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'delivery_fee', 'min_order_amount', 'estimated_minutes_min', 'estimated_minutes_max']);

        $customer = auth('customer')->user();
        $savedAddresses = $customer
            ? $customer->addresses()->orderByDesc('is_default')->latest('id')->get([
                'id', 'street', 'apartment', 'entrance', 'floor', 'intercom', 'instructions', 'is_default',
            ])
            : collect();

        $loyalty = null;
        if ($customer && (bool) Settings::get('loyalty.enabled', true)) {
            $account = LoyaltyAccount::forCustomer($customer);
            $loyalty = [
                'balance' => (float) $account->balance,
                'max_spend_percent' => (float) Settings::get('loyalty.max_spend_percent_per_order', 30),
            ];
        }

        return Inertia::render('catalog/checkout', [
            'establishment' => [
                'name' => Settings::get('general.name'),
                'phone' => Settings::get('general.phone'),
                'accepting_orders' => Settings::isAcceptingOrders(),
                'closed_reason' => Settings::closedReason(),
                'min_order_amount' => (float) Settings::get('orders.min_order_amount', 0),
            ],
            'zones' => $zones,
            'saved_addresses' => $savedAddresses,
            'loyalty' => $loyalty,
        ]);
    }

    public function loyalty(): Response
    {
        $levels = LoyaltyLevel::query()->ordered()->get(['id', 'name', 'min_lifetime_spend', 'cashback_percent']);

        $customer = auth('customer')->user();
        $balance = null;
        if ($customer) {
            $balance = (float) LoyaltyAccount::forCustomer($customer)->balance;
        }

        return Inertia::render('catalog/loyalty', [
            'establishment' => [
                'name' => Settings::get('general.name'),
                'phone' => Settings::get('general.phone'),
                'accepting_orders' => Settings::isAcceptingOrders(),
                'closed_reason' => Settings::closedReason(),
            ],
            'levels' => $levels,
            'is_enabled' => (bool) Settings::get('loyalty.enabled', true),
            'max_spend_percent' => (float) Settings::get('loyalty.max_spend_percent_per_order', 30),
            'customer_balance' => $balance,
        ]);
    }
}
