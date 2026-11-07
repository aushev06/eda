<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function enter(string $token, Request $request): RedirectResponse
    {
        $table = Table::query()->active()->where('qr_token', $token)->firstOrFail();

        $request->session()->put('table_id', $table->id);

        $isOpenForGuests = Order::query()
            ->openTableCheck($table->id)
            ->whereNotNull('guest_pin')
            ->exists();

        return $isOpenForGuests
            ? redirect()->route('table.show')
            : redirect()->route('home');
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->forget(['table_id', 'table_guest_id']);

        return back();
    }
}
