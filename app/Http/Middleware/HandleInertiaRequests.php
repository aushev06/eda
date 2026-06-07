<?php

namespace App\Http\Middleware;

use App\Models\CustomerNotification;
use App\Models\Table;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'customer' => fn () => $request->user('customer')?->only(['id', 'name', 'phone', 'email']),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'dev_otp' => fn () => $request->session()->get('dev_otp'),
            ],
            'table' => fn () => $this->resolveTable($request),
            'notifications' => fn () => $this->resolveNotifications($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{recent: array<int, array<string, mixed>>, unread_count: int}|null
     */
    protected function resolveNotifications(Request $request): ?array
    {
        $customer = $request->user('customer');
        if (! $customer) {
            return null;
        }

        $recent = CustomerNotification::query()
            ->forCustomer($customer->id)
            ->latest('id')
            ->limit(10)
            ->get(['id', 'type', 'title', 'body', 'action_url', 'read_at', 'created_at']);

        $unread = CustomerNotification::query()
            ->forCustomer($customer->id)
            ->unread()
            ->count();

        return [
            'recent' => $recent->map(fn (CustomerNotification $n) => [
                'id' => $n->id,
                'type' => $n->type->value,
                'title' => $n->title,
                'body' => $n->body,
                'action_url' => $n->action_url,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
            'unread_count' => $unread,
        ];
    }

    /**
     * @return array{id: int, number: int, label: string|null}|null
     */
    protected function resolveTable(Request $request): ?array
    {
        $id = $request->session()->get('table_id');
        if (! $id) {
            return null;
        }

        $table = Table::query()->active()->find($id);
        if (! $table) {
            // Stale session pointing at a deleted/deactivated table — purge it.
            $request->session()->forget('table_id');

            return null;
        }

        return [
            'id' => $table->id,
            'number' => $table->number,
            'label' => $table->label,
        ];
    }
}
