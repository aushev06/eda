import { useState } from 'react';

type Tender = {
    method: 'cash' | 'card_online';
    amount: number;
    received_amount?: number;
};

type Props = {
    total: number;
    busy?: boolean;
    onSettle: (tenders: Tender[]) => void;
    onCancel: () => void;
};

function formatPrice(value: number): string {
    return `${value.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ₽`;
}

const round2 = (n: number) => Math.round(n * 100) / 100;

/**
 * Split-tender payment pad with idiot-proofing. The cashier enters how much
 * goes on card (exact, part of the bill) and how much cash the guest handed.
 * Guard rails:
 *  - card is capped at the bill — you can't charge more than the order;
 *  - when card covers the whole bill, the cash field is locked (no phantom change);
 *  - "Оплатить" is disabled until the entered money actually covers the bill;
 *  - change shows only when cash is genuinely owed and overpaid.
 */
export default function PaymentScreen({ total, busy, onSettle, onCancel }: Props) {
    const [active, setActive] = useState<'cash' | 'card'>('cash');
    const [cardStr, setCardStr] = useState('');
    const [cashStr, setCashStr] = useState('');

    const cardRaw = Math.max(0, parseFloat(cardStr) || 0);
    const card = Math.min(cardRaw, total); // never charge more than the bill
    const cardOver = cardRaw > total + 0.001; // cashier typed more than the order
    const cashDue = round2(total - card); // >= 0 because card <= total
    const cardCoversAll = cashDue <= 0.001; // card already pays the whole bill
    const cashReceived = cardCoversAll ? 0 : Math.max(0, parseFloat(cashStr) || 0);
    const remaining = Math.max(0, round2(cashDue - cashReceived));
    const change = cashDue > 0 ? Math.max(0, round2(cashReceived - cashDue)) : 0;
    const covered = remaining <= 0.001;

    // When card covers everything, the cash field is locked — force focus to card.
    const activeField: 'cash' | 'card' = cardCoversAll ? 'card' : active;

    function tapDigit(d: string) {
        const setter = activeField === 'cash' ? setCashStr : setCardStr;
        const cur = activeField === 'cash' ? cashStr : cardStr;

        if (d === '.' && cur.includes('.')) {
            return;
        }

        // Cap fractional input at 2 decimals.
        if (cur.includes('.') && cur.split('.')[1]?.length >= 2 && d !== '.') {
            return;
        }

        setter((cur + d).replace(/^0(\d)/, '$1'));
    }

    function backspace() {
        const setter = activeField === 'cash' ? setCashStr : setCardStr;
        const cur = activeField === 'cash' ? cashStr : cardStr;
        setter(cur.slice(0, -1));
    }

    function clearActive() {
        (activeField === 'cash' ? setCashStr : setCardStr)('');
    }

    function payExactCard() {
        setCardStr(String(total));
        setCashStr('');
        setActive('card');
    }

    function payExactCash() {
        setCardStr('');
        setCashStr(String(total));
        setActive('cash');
    }

    function settle() {
        if (!covered || busy) {
            return;
        }

        const tenders: Tender[] = [];

        if (card > 0) {
            tenders.push({ method: 'card_online', amount: round2(card) });
        }

        if (cashDue > 0) {
            tenders.push({ method: 'cash', amount: cashDue, received_amount: round2(cashReceived) });
        }

        if (tenders.length === 0) {
            return;
        }

        onSettle(tenders);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4" onClick={onCancel}>
            <div
                className="flex max-h-[92vh] w-full max-w-md flex-col overflow-y-auto rounded-t-2xl bg-white p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] sm:rounded-2xl sm:pb-4"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="mb-3 flex items-baseline justify-between">
                    <h2 className="text-lg font-bold">К оплате</h2>
                    <span className="text-2xl font-bold tabular-nums">{formatPrice(total)}</span>
                </div>

                {/* Two method fields */}
                <div className="mb-2 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        onClick={() => !cardCoversAll && setActive('cash')}
                        disabled={cardCoversAll}
                        className={`rounded-xl border-2 p-2 text-left disabled:opacity-40 ${
                            activeField === 'cash' ? 'border-zinc-900' : 'border-zinc-300'
                        }`}
                    >
                        <div className="text-xs text-zinc-500">Наличными (получено)</div>
                        <div className="text-xl font-bold tabular-nums">{cardCoversAll ? '—' : cashStr || '0'}</div>
                    </button>
                    <button
                        type="button"
                        onClick={() => setActive('card')}
                        className={`rounded-xl border-2 p-2 text-left ${activeField === 'card' ? 'border-zinc-900' : 'border-zinc-300'}`}
                    >
                        <div className="text-xs text-zinc-500">Картой</div>
                        <div className="text-xl font-bold tabular-nums">{cardStr || '0'}</div>
                    </button>
                </div>

                {/* Idiot-proofing hints */}
                {cardOver && (
                    <p className="mb-2 rounded-lg bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-700">
                        Картой нельзя больше суммы заказа — учтём {formatPrice(total)}.
                    </p>
                )}
                {cardCoversAll && card > 0 && (
                    <p className="mb-2 rounded-lg bg-zinc-100 px-3 py-1.5 text-sm text-zinc-600">Карта покрывает весь счёт — наличные не нужны.</p>
                )}

                {/* Live indicators */}
                <div className="mb-3 flex justify-between rounded-lg bg-zinc-100 px-3 py-2 text-sm">
                    {remaining > 0 ? (
                        <span className="font-bold text-red-600">
                            Не хватает: <span className="tabular-nums">{formatPrice(remaining)}</span>
                        </span>
                    ) : (
                        <span className="text-zinc-500">
                            Наличными к оплате: <b className="tabular-nums">{formatPrice(cashDue)}</b>
                        </span>
                    )}
                    <span className={change > 0 ? 'font-bold text-emerald-600' : 'text-zinc-500'}>
                        Сдача: <b className="tabular-nums">{formatPrice(change)}</b>
                    </span>
                </div>

                {/* Quick buttons */}
                <div className="mb-2 grid grid-cols-2 gap-2">
                    <button type="button" onClick={payExactCash} className="h-11 rounded-lg border-2 border-zinc-400 text-sm font-semibold">
                        Без сдачи (нал)
                    </button>
                    <button type="button" onClick={payExactCard} className="h-11 rounded-lg border-2 border-zinc-400 text-sm font-semibold">
                        Картой
                    </button>
                </div>

                {/* Numpad */}
                <div className="grid grid-cols-3 gap-1.5">
                    {['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', '⌫'].map((k) => (
                        <button
                            key={k}
                            type="button"
                            onClick={() => (k === '⌫' ? backspace() : tapDigit(k))}
                            className="h-14 rounded-lg border-2 border-zinc-300 text-xl font-semibold active:bg-zinc-200"
                        >
                            {k}
                        </button>
                    ))}
                </div>

                <div className="mt-3 flex gap-2">
                    <button type="button" onClick={onCancel} className="h-14 flex-1 rounded-xl border-2 border-zinc-300 text-base font-semibold">
                        Отмена
                    </button>
                    <button type="button" onClick={clearActive} className="h-14 w-20 rounded-xl border-2 border-zinc-300 text-base font-semibold">
                        Сброс
                    </button>
                    <button
                        type="button"
                        onClick={settle}
                        disabled={!covered || busy}
                        className="h-14 flex-[1.5] rounded-xl bg-emerald-600 text-base font-bold text-white disabled:opacity-30"
                    >
                        {busy ? 'Оплата…' : 'Оплатить'}
                    </button>
                </div>
            </div>
        </div>
    );
}
