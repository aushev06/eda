<?php

namespace App\Http\Controllers;

use App\Actions\Pos\AddItemsToCheck;
use App\Actions\Pos\JoinTable;
use App\Actions\Pos\ResolveTableGuest;
use App\Actions\Pos\SerializeGuestCheck;
use App\Http\Requests\StoreTableRoundRequest;
use App\Models\Order;
use App\Models\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TableGuestController extends Controller
{
    /**
     * Shared table page: nothing to do yet, the PIN form, or the live check.
     */
    public function show(Request $request, ResolveTableGuest $resolve, SerializeGuestCheck $serializer): Response|RedirectResponse
    {
        $table = $this->currentTable($request);

        if (! $table) {
            return redirect()->route('home');
        }

        $guest = $resolve->handle($request);
        $customer = $request->user('customer');

        // A guest who signs in mid-visit is still the same person at the table.
        if ($guest && $customer && $guest->customer_id === null) {
            $guest->update(['customer_id' => $customer->id]);
        }

        $isOpenForGuests = $guest !== null || Order::query()
            ->openTableCheck($table->id)
            ->whereNotNull('guest_pin')
            ->exists();

        return Inertia::render('catalog/table', [
            'table_info' => ['number' => $table->number, 'label' => $table->label],
            'state' => $guest ? 'joined' : ($isOpenForGuests ? 'join' : 'closed'),
            'guest_name' => $guest?->name,
            'default_name' => $customer?->name,
            'check' => $guest ? $serializer->handle($guest->order, $guest) : null,
        ]);
    }

    public function join(Request $request, JoinTable $join): RedirectResponse
    {
        $table = $this->currentTable($request);

        if (! $table) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'pin' => ['required', 'string', 'digits:4'],
        ]);

        $guest = $join->handle($table, $data['pin'], trim($data['name']), $request->user('customer'));

        $request->session()->put('table_guest_id', $guest->id);

        return redirect()->route('table.show');
    }

    public function storeItems(StoreTableRoundRequest $request, ResolveTableGuest $resolve, AddItemsToCheck $addItems): RedirectResponse
    {
        $guest = $resolve->handle($request);

        if (! $guest) {
            throw ValidationException::withMessages([
                'items' => 'Заказы с телефона за этим столом закрыты. Обратитесь к официанту.',
            ]);
        }

        $addItems->handle($guest->order, $request->validated()['items'], $guest);

        return redirect()->route('table.show');
    }

    protected function currentTable(Request $request): ?Table
    {
        $tableId = $request->session()->get('table_id');

        return $tableId ? Table::query()->active()->find($tableId) : null;
    }
}
