<?php

namespace App\Http\Middleware;

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
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
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
