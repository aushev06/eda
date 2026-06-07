<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerNotification;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function index(): Response
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $notifications = CustomerNotification::query()
            ->forCustomer($customer->id)
            ->latest('id')
            ->get(['id', 'type', 'title', 'body', 'action_url', 'data', 'read_at', 'created_at'])
            ->map(fn (CustomerNotification $n) => [
                'id' => $n->id,
                'type' => $n->type->value,
                'title' => $n->title,
                'body' => $n->body,
                'action_url' => $n->action_url,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return Inertia::render('account/notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(CustomerNotification $notification): RedirectResponse
    {
        abort_unless($notification->customer_id === auth('customer')->id(), 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return redirect()->back();
    }

    public function markAllRead(): RedirectResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        CustomerNotification::query()
            ->forCustomer($customer->id)
            ->unread()
            ->update(['read_at' => now()]);

        return redirect()->back();
    }
}
