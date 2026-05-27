<?php

namespace App\Http\Controllers;

use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function enter(string $token, Request $request): RedirectResponse
    {
        $table = Table::query()->active()->where('qr_token', $token)->firstOrFail();

        $request->session()->put('table_id', $table->id);

        return redirect()->route('home');
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->forget('table_id');

        return back();
    }
}
