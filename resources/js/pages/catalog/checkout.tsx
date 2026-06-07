import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, Gift, ShoppingBag, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PhoneInput } from '@/components/forms/phone-input';
import { CartProvider, useCart } from '@/hooks/catalog/use-cart';
import { formatRub } from '@/lib/format';
import { store as ordersStore } from '@/routes/orders';
import type { Establishment } from '@/types/catalog';

type AppliedPromo = {
    code: string;
    description: string | null;
    discount: number;
};

type Zone = {
    id: number;
    name: string;
    delivery_fee: string;
    min_order_amount: string;
    estimated_minutes_min: number;
    estimated_minutes_max: number;
};

type SavedAddress = {
    id: number;
    street: string;
    apartment: string | null;
    entrance: string | null;
    floor: string | null;
    intercom: string | null;
    instructions: string | null;
    is_default: boolean;
};

type Loyalty = {
    balance: number;
    max_spend_percent: number;
};

type Props = {
    establishment: Establishment;
    zones: Zone[];
    saved_addresses: SavedAddress[];
    loyalty: Loyalty | null;
};

type SharedAuth = {
    auth: { customer: { name: string; phone: string; email: string | null } | null };
    table: { id: number; number: number; label: string | null } | null;
};

export default function CheckoutPage({ establishment, zones, saved_addresses, loyalty }: Props) {
    return (
        <CartProvider>
            <Head title="Оформление заказа" />
            <Checkout
                establishment={establishment}
                zones={zones}
                saved_addresses={saved_addresses}
                loyalty={loyalty}
            />
        </CartProvider>
    );
}

type FormPayload = {
    customer_name: string;
    customer_phone: string;
    delivery_type: 'delivery' | 'pickup' | 'dine_in';
    payment_method: 'cash' | 'card_online' | 'card_courier';
    customer_comment: string;
    promo_code: string;
    bonus_to_use: number;
    table_id: number | null;
    items: Array<{ product_id: number; quantity: number; modifier_ids: number[] }>;
    delivery: {
        zone_id: number | null;
        street: string;
        apartment: string;
        entrance: string;
        floor: string;
        intercom: string;
        instructions: string;
    };
};

function Checkout({ establishment, zones, saved_addresses, loyalty }: Props) {
    const { lines, subtotal, count, clear } = useCart();
    const { auth, table } = usePage<SharedAuth>().props;
    const authCustomer = auth.customer;
    const defaultAddress = saved_addresses.find((a) => a.is_default) ?? saved_addresses[0] ?? null;

    // If a QR table is active in the session, the order is dine-in and locked
    // to that table. Otherwise default to delivery (if zones exist) or pickup.
    const initialDeliveryType: FormPayload['delivery_type'] = table
        ? 'dine_in'
        : zones.length > 0
            ? 'delivery'
            : 'pickup';

    const form = useForm<FormPayload>({
        customer_name: authCustomer?.name ?? '',
        customer_phone: authCustomer?.phone ?? '',
        delivery_type: initialDeliveryType,
        payment_method: 'cash',
        customer_comment: '',
        promo_code: '',
        bonus_to_use: 0,
        table_id: table?.id ?? null,
        items: [],
        delivery: {
            zone_id: zones[0]?.id ?? null,
            street: defaultAddress?.street ?? '',
            apartment: defaultAddress?.apartment ?? '',
            entrance: defaultAddress?.entrance ?? '',
            floor: defaultAddress?.floor ?? '',
            intercom: defaultAddress?.intercom ?? '',
            instructions: defaultAddress?.instructions ?? '',
        },
    });

    const [selectedAddressId, setSelectedAddressId] = useState<number | 'new'>(defaultAddress?.id ?? 'new');

    function selectSavedAddress(addressId: number | 'new') {
        setSelectedAddressId(addressId);
        if (addressId === 'new') {
            form.setData('delivery', {
                ...form.data.delivery,
                street: '',
                apartment: '',
                entrance: '',
                floor: '',
                intercom: '',
                instructions: '',
            });
            return;
        }
        const a = saved_addresses.find((x) => x.id === addressId);
        if (!a) return;
        form.setData('delivery', {
            ...form.data.delivery,
            street: a.street,
            apartment: a.apartment ?? '',
            entrance: a.entrance ?? '',
            floor: a.floor ?? '',
            intercom: a.intercom ?? '',
            instructions: a.instructions ?? '',
        });
    }

    const [promoInput, setPromoInput] = useState('');
    const [promoLoading, setPromoLoading] = useState(false);
    const [promoError, setPromoError] = useState<string | null>(null);
    const [appliedPromo, setAppliedPromo] = useState<AppliedPromo | null>(null);

    useEffect(() => {
        form.setData(
            'items',
            lines.map((l) => ({
                product_id: l.product_id,
                quantity: l.quantity,
                modifier_ids: l.modifiers.map((m) => m.modifier_id),
            })),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [lines]);

    const selectedZone = useMemo(
        () => zones.find((z) => z.id === form.data.delivery.zone_id) ?? null,
        [zones, form.data.delivery.zone_id],
    );

    const deliveryFee =
        form.data.delivery_type === 'delivery' && selectedZone ? Number(selectedZone.delivery_fee) : 0;
    const discount = appliedPromo ? appliedPromo.discount : 0;

    const bonusCap = useMemo(() => {
        if (!loyalty || subtotal <= 0) return 0;
        return Math.min(loyalty.balance, Math.floor((subtotal * loyalty.max_spend_percent) / 100));
    }, [loyalty, subtotal]);

    // Clamp bonus_to_use whenever cap changes (cart edits etc).
    useEffect(() => {
        if (form.data.bonus_to_use > bonusCap) {
            form.setData('bonus_to_use', bonusCap);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [bonusCap]);

    const bonusUsed = Math.max(0, Math.min(form.data.bonus_to_use, bonusCap));
    const total = Math.max(0, subtotal + deliveryFee - discount - bonusUsed);

    // Re-validate the applied promo whenever cart subtotal or phone changes —
    // server-side rules (min_order_amount, first_order_only) depend on them.
    useEffect(() => {
        if (!appliedPromo) return;
        let cancelled = false;

        const run = async () => {
            try {
                const res = await fetch('/promo-codes/validate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        code: appliedPromo.code,
                        subtotal,
                        customer_phone: form.data.customer_phone || null,
                    }),
                });
                if (cancelled) return;
                if (!res.ok) {
                    setAppliedPromo(null);
                    const body = await res.json().catch(() => ({}));
                    setPromoError(body?.message ?? 'Промокод больше не применим.');
                    return;
                }
                const body = await res.json();
                setAppliedPromo({ code: body.code, description: body.description, discount: Number(body.discount) });
            } catch {
                // silently keep current state on network failure
            }
        };

        void run();
        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [subtotal, form.data.customer_phone]);

    async function applyPromo() {
        const code = promoInput.trim();
        if (!code || promoLoading) return;
        setPromoError(null);
        setPromoLoading(true);
        try {
            const res = await fetch('/promo-codes/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    code,
                    subtotal,
                    customer_phone: form.data.customer_phone || null,
                }),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok || !body.valid) {
                setAppliedPromo(null);
                setPromoError(body?.message ?? 'Промокод недействителен.');
                form.setData('promo_code', '');
                return;
            }
            setAppliedPromo({ code: body.code, description: body.description, discount: Number(body.discount) });
            form.setData('promo_code', body.code);
        } catch {
            setPromoError('Не удалось проверить промокод. Попробуйте ещё раз.');
        } finally {
            setPromoLoading(false);
        }
    }

    function removePromo() {
        setAppliedPromo(null);
        setPromoInput('');
        setPromoError(null);
        form.setData('promo_code', '');
    }

    const zoneMin = selectedZone ? Number(selectedZone.min_order_amount) : 0;
    const belowZoneMin = form.data.delivery_type === 'delivery' && selectedZone && subtotal < zoneMin;

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        form.post(ordersStore.url(), {
            preserveScroll: true,
            onSuccess: () => clear(),
        });
    }

    if (count === 0) {
        return <EmptyCheckout />;
    }

    return (
        <div className="min-h-screen bg-stone-50 text-stone-900">
            <header className="border-b border-stone-200 bg-white">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                    <Link href="/" className="inline-flex items-center gap-2 text-sm text-stone-600 hover:text-stone-900">
                        <ArrowLeft className="size-4" />
                        К меню
                    </Link>
                    <span className="text-base font-semibold">{establishment.name}</span>
                </div>
            </header>

            <main className="mx-auto grid max-w-5xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[1fr_360px]">
                <form onSubmit={handleSubmit} className="space-y-6">
                    {!establishment.accepting_orders && (
                        <Banner tone="danger" title="Заказы временно не принимаются">
                            {establishment.closed_reason ?? 'Загляните чуть позже.'}
                        </Banner>
                    )}

                    <Section title="Контакты">
                        <Field
                            label="Ваше имя"
                            required
                            value={form.data.customer_name}
                            onChange={(v) => form.setData('customer_name', v)}
                            error={form.errors.customer_name}
                            placeholder="Иван"
                        />
                        <PhoneInput
                            value={form.data.customer_phone}
                            onChange={(v) => form.setData('customer_phone', v)}
                            error={form.errors.customer_phone}
                            required
                        />
                    </Section>

                    {table ? (
                        <Section title="Способ получения">
                            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <p className="text-sm font-semibold text-emerald-900">
                                    Заказ на столик {table.number}
                                    {table.label ? ` (${table.label})` : ''}
                                </p>
                                <p className="mt-1 text-xs text-emerald-800">
                                    Принесём заказ прямо к вам — никаких адресов и доставки не нужно.
                                </p>
                            </div>
                        </Section>
                    ) : (
                        <Section title="Способ получения">
                            <div className="grid grid-cols-2 gap-2">
                                <TabButton
                                    active={form.data.delivery_type === 'delivery'}
                                    onClick={() => form.setData('delivery_type', 'delivery')}
                                    disabled={zones.length === 0}
                                >
                                    Доставка
                                </TabButton>
                                <TabButton
                                    active={form.data.delivery_type === 'pickup'}
                                    onClick={() => form.setData('delivery_type', 'pickup')}
                                >
                                    Самовывоз
                                </TabButton>
                            </div>
                            {form.errors.delivery_type && (
                                <p className="mt-2 text-xs text-rose-600">{form.errors.delivery_type}</p>
                            )}
                        </Section>
                    )}

                    {form.data.delivery_type === 'delivery' && (
                        <Section title="Адрес доставки">
                            {saved_addresses.length > 0 && (
                                <div>
                                    <label className="mb-1.5 block text-xs font-medium text-stone-700">
                                        Сохранённые адреса
                                    </label>
                                    <div className="grid gap-2">
                                        {saved_addresses.map((address) => {
                                            const checked = selectedAddressId === address.id;
                                            return (
                                                <label
                                                    key={address.id}
                                                    className={`flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 transition ${
                                                        checked
                                                            ? 'border-amber-400 bg-amber-50'
                                                            : 'border-stone-200 bg-white hover:border-stone-300'
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="saved-address"
                                                        className="mt-1 size-4 accent-amber-500"
                                                        checked={checked}
                                                        onChange={() => selectSavedAddress(address.id)}
                                                    />
                                                    <div className="text-sm">
                                                        <p className="font-medium">{address.street}</p>
                                                        <p className="text-xs text-stone-500">
                                                            {[
                                                                address.apartment && `кв. ${address.apartment}`,
                                                                address.entrance && `подъезд ${address.entrance}`,
                                                                address.floor && `этаж ${address.floor}`,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </p>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                        <label
                                            className={`flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition ${
                                                selectedAddressId === 'new'
                                                    ? 'border-amber-400 bg-amber-50'
                                                    : 'border-stone-200 bg-white hover:border-stone-300'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="saved-address"
                                                className="size-4 accent-amber-500"
                                                checked={selectedAddressId === 'new'}
                                                onChange={() => selectSavedAddress('new')}
                                            />
                                            <span className="text-sm font-medium">+ Новый адрес</span>
                                        </label>
                                    </div>
                                </div>
                            )}

                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-stone-700">
                                    Зона <span className="text-rose-600">*</span>
                                </label>
                                <div className="grid gap-2">
                                    {zones.map((zone) => {
                                        const checked = form.data.delivery.zone_id === zone.id;
                                        return (
                                            <label
                                                key={zone.id}
                                                className={`flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3 py-2.5 transition ${
                                                    checked
                                                        ? 'border-amber-400 bg-amber-50'
                                                        : 'border-stone-200 bg-white hover:border-stone-300'
                                                }`}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <input
                                                        type="radio"
                                                        name="zone"
                                                        className="size-4 accent-amber-500"
                                                        checked={checked}
                                                        onChange={() =>
                                                            form.setData('delivery', {
                                                                ...form.data.delivery,
                                                                zone_id: zone.id,
                                                            })
                                                        }
                                                    />
                                                    <div>
                                                        <p className="text-sm font-medium">{zone.name}</p>
                                                        <p className="text-xs text-stone-500">
                                                            Мин. {formatRub(zone.min_order_amount)} · {zone.estimated_minutes_min}–{zone.estimated_minutes_max} мин
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="text-sm font-semibold">{formatRub(zone.delivery_fee)}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                                {form.errors['delivery.zone_id'] && (
                                    <p className="mt-1.5 text-xs text-rose-600">{form.errors['delivery.zone_id']}</p>
                                )}
                            </div>

                            <Field
                                label="Улица и дом"
                                required
                                value={form.data.delivery.street}
                                onChange={(v) =>
                                    form.setData('delivery', { ...form.data.delivery, street: v })
                                }
                                error={form.errors['delivery.street']}
                                placeholder="Тверская ул., 1"
                            />
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <Field
                                    label="Кв."
                                    value={form.data.delivery.apartment}
                                    onChange={(v) =>
                                        form.setData('delivery', { ...form.data.delivery, apartment: v })
                                    }
                                />
                                <Field
                                    label="Подъезд"
                                    value={form.data.delivery.entrance}
                                    onChange={(v) =>
                                        form.setData('delivery', { ...form.data.delivery, entrance: v })
                                    }
                                />
                                <Field
                                    label="Этаж"
                                    value={form.data.delivery.floor}
                                    onChange={(v) =>
                                        form.setData('delivery', { ...form.data.delivery, floor: v })
                                    }
                                />
                                <Field
                                    label="Домофон"
                                    value={form.data.delivery.intercom}
                                    onChange={(v) =>
                                        form.setData('delivery', { ...form.data.delivery, intercom: v })
                                    }
                                />
                            </div>
                            <Field
                                label="Инструкции курьеру"
                                value={form.data.delivery.instructions}
                                onChange={(v) =>
                                    form.setData('delivery', { ...form.data.delivery, instructions: v })
                                }
                                placeholder="Позвонить за 10 минут"
                            />
                        </Section>
                    )}

                    <Section title="Оплата">
                        <div className="grid gap-2">
                            {(['cash', 'card_courier', 'card_online'] as const).map((method) => (
                                <label
                                    key={method}
                                    className={`flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition ${
                                        form.data.payment_method === method
                                            ? 'border-amber-400 bg-amber-50'
                                            : 'border-stone-200 bg-white hover:border-stone-300'
                                    }`}
                                >
                                    <input
                                        type="radio"
                                        name="payment"
                                        className="size-4 accent-amber-500"
                                        checked={form.data.payment_method === method}
                                        onChange={() => form.setData('payment_method', method)}
                                    />
                                    <span className="text-sm">{paymentLabel(method)}</span>
                                </label>
                            ))}
                        </div>
                    </Section>

                    {loyalty && loyalty.balance > 0 && bonusCap > 0 && (
                        <Section title="Бонусы">
                            <div className="rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-50 p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="flex gap-3">
                                        <Gift className="mt-0.5 size-5 shrink-0 text-amber-600" />
                                        <div>
                                            <p className="text-sm font-semibold text-stone-900">
                                                Доступно {formatRub(loyalty.balance)} бонусов
                                            </p>
                                            <p className="text-xs text-stone-600">
                                                Можно списать до {formatRub(bonusCap)} в этом заказе
                                                ({loyalty.max_spend_percent}% от стоимости товаров).
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-3 flex gap-2">
                                    <input
                                        type="number"
                                        min={0}
                                        max={bonusCap}
                                        step={1}
                                        value={form.data.bonus_to_use || ''}
                                        onChange={(e) => {
                                            const value = Math.max(0, Math.min(bonusCap, Number(e.target.value || 0)));
                                            form.setData('bonus_to_use', value);
                                        }}
                                        placeholder="0"
                                        className="flex-1 rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm text-stone-900 placeholder:text-stone-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => form.setData('bonus_to_use', bonusCap)}
                                        className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-800"
                                    >
                                        Максимум
                                    </button>
                                </div>
                                {form.errors.bonus_to_use && (
                                    <p className="mt-1.5 text-xs text-rose-600">{form.errors.bonus_to_use}</p>
                                )}
                            </div>
                        </Section>
                    )}

                    <Section title="Промокод">
                        {appliedPromo ? (
                            <div className="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5">
                                <div className="flex items-start gap-2">
                                    <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                    <div>
                                        <p className="text-sm font-semibold text-emerald-900">{appliedPromo.code}</p>
                                        {appliedPromo.description && (
                                            <p className="text-xs text-emerald-800">{appliedPromo.description}</p>
                                        )}
                                        <p className="mt-0.5 text-xs text-emerald-700">
                                            Скидка {formatRub(appliedPromo.discount)}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={removePromo}
                                    className="text-emerald-700 hover:text-emerald-900"
                                    aria-label="Убрать промокод"
                                >
                                    <X className="size-4" />
                                </button>
                            </div>
                        ) : (
                            <div>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={promoInput}
                                        onChange={(e) => {
                                            setPromoInput(e.target.value.toUpperCase());
                                            setPromoError(null);
                                        }}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                void applyPromo();
                                            }
                                        }}
                                        placeholder="WELCOME10"
                                        className="flex-1 rounded-xl border border-stone-200 px-3 py-2.5 text-sm uppercase tracking-wider focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => void applyPromo()}
                                        disabled={promoLoading || !promoInput.trim()}
                                        className="rounded-xl bg-stone-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-stone-800 disabled:opacity-50"
                                    >
                                        {promoLoading ? '…' : 'Применить'}
                                    </button>
                                </div>
                                {promoError && <p className="mt-1.5 text-xs text-rose-600">{promoError}</p>}
                            </div>
                        )}
                    </Section>

                    <Section title="Комментарий">
                        <textarea
                            value={form.data.customer_comment}
                            onChange={(e) => form.setData('customer_comment', e.target.value)}
                            rows={3}
                            placeholder="Пожелания к заказу"
                            className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                        />
                    </Section>

                    {form.errors.items && (
                        <Banner tone="danger" title="Не получилось оформить">
                            {form.errors.items}
                        </Banner>
                    )}
                </form>

                <aside className="lg:sticky lg:top-6 lg:self-start">
                    <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
                        <h3 className="text-base font-semibold">Ваш заказ</h3>
                        <ul className="mt-3 divide-y divide-stone-100 text-sm">
                            {lines.map((line) => (
                                <li key={line.id} className="flex justify-between gap-3 py-2.5">
                                    <div>
                                        <p className="font-medium text-stone-900">
                                            {line.product_name}{' '}
                                            <span className="text-stone-400">× {line.quantity}</span>
                                        </p>
                                        {line.modifiers.length > 0 && (
                                            <p className="text-xs text-stone-500">
                                                {line.modifiers.map((m) => m.name).join(', ')}
                                            </p>
                                        )}
                                    </div>
                                    <span className="shrink-0 font-medium">
                                        {formatRub(
                                            (line.unit_price + line.modifiers_total_per_unit) * line.quantity,
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        <dl className="mt-4 space-y-1.5 border-t border-stone-100 pt-4 text-sm">
                            <Row label="Товары" value={formatRub(subtotal)} />
                            {form.data.delivery_type === 'delivery' && (
                                <Row label="Доставка" value={selectedZone ? formatRub(deliveryFee) : '—'} />
                            )}
                            {appliedPromo && (
                                <Row
                                    label={`Скидка · ${appliedPromo.code}`}
                                    value={`−${formatRub(discount)}`}
                                    accent="success"
                                />
                            )}
                            {bonusUsed > 0 && (
                                <Row label="Бонусы" value={`−${formatRub(bonusUsed)}`} accent="success" />
                            )}
                            <Row label="Итого" value={formatRub(total)} bold />
                        </dl>

                        {belowZoneMin && (
                            <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">
                                Минимум для зоны «{selectedZone?.name}» — {formatRub(zoneMin)}.
                            </p>
                        )}

                        <button
                            type="submit"
                            onClick={handleSubmit}
                            disabled={form.processing || !!belowZoneMin || !establishment.accepting_orders}
                            className="mt-4 w-full rounded-full bg-stone-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-stone-800 disabled:opacity-50"
                        >
                            {form.processing ? 'Отправляем…' : `Оформить · ${formatRub(total)}`}
                        </button>
                    </div>
                </aside>
            </main>
        </div>
    );
}

function EmptyCheckout() {
    return (
        <main className="grid min-h-screen place-items-center bg-stone-50 px-6">
            <div className="max-w-md text-center">
                <ShoppingBag className="mx-auto size-12 text-stone-300" />
                <h1 className="mt-4 text-xl font-semibold">Корзина пуста</h1>
                <p className="mt-2 text-sm text-stone-500">Выберите блюда в меню и вернитесь сюда.</p>
                <Link
                    href="/"
                    className="mt-6 inline-flex items-center gap-2 rounded-full bg-stone-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-stone-800"
                >
                    <ArrowLeft className="size-4" />
                    К меню
                </Link>
            </div>
        </main>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <section className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <h2 className="mb-4 text-base font-semibold">{title}</h2>
            <div className="space-y-3">{children}</div>
        </section>
    );
}

function Field({
    label,
    required,
    type = 'text',
    value,
    onChange,
    error,
    placeholder,
}: {
    label: string;
    required?: boolean;
    type?: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
    placeholder?: string;
}) {
    return (
        <div>
            <label className="mb-1.5 block text-xs font-medium text-stone-700">
                {label}
                {required && <span className="ml-0.5 text-rose-600">*</span>}
            </label>
            <input
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={`w-full rounded-xl border px-3 py-2.5 text-sm focus:outline-none focus:ring-2 ${
                    error
                        ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                        : 'border-stone-200 focus:border-amber-400 focus:ring-amber-100'
                }`}
            />
            {error && <p className="mt-1 text-xs text-rose-600">{error}</p>}
        </div>
    );
}

function TabButton({
    active,
    onClick,
    disabled,
    children,
}: {
    active: boolean;
    onClick: () => void;
    disabled?: boolean;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={`rounded-xl px-4 py-3 text-sm font-medium transition ${
                active ? 'bg-stone-900 text-white' : 'bg-stone-100 text-stone-700 hover:bg-stone-200'
            } disabled:cursor-not-allowed disabled:opacity-50`}
        >
            {children}
        </button>
    );
}

function Row({
    label,
    value,
    bold,
    accent,
}: {
    label: string;
    value: string;
    bold?: boolean;
    accent?: 'success';
}) {
    const cls = bold
        ? 'text-base font-semibold text-stone-900'
        : accent === 'success'
            ? 'text-emerald-700'
            : 'text-stone-600';
    return (
        <div className={`flex justify-between ${cls}`}>
            <dt>{label}</dt>
            <dd>{value}</dd>
        </div>
    );
}

function Banner({
    tone,
    title,
    children,
}: {
    tone: 'danger' | 'warn';
    title: string;
    children: React.ReactNode;
}) {
    const cls =
        tone === 'danger'
            ? 'border-rose-200 bg-rose-50 text-rose-900'
            : 'border-amber-200 bg-amber-50 text-amber-900';
    return (
        <div className={`rounded-2xl border px-4 py-3 ${cls}`}>
            <p className="font-medium">{title}</p>
            <p className="mt-0.5 text-sm">{children}</p>
        </div>
    );
}

function paymentLabel(method: 'cash' | 'card_online' | 'card_courier'): string {
    switch (method) {
        case 'cash':
            return 'Наличные';
        case 'card_courier':
            return 'Карта курьеру';
        case 'card_online':
            return 'Карта онлайн';
    }
}
