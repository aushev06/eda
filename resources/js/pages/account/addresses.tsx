import { Head, router, useForm } from '@inertiajs/react';
import { MapPin, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AccountLayout } from '@/components/account/account-layout';

type Address = {
    id: number;
    street: string;
    apartment: string | null;
    entrance: string | null;
    floor: string | null;
    intercom: string | null;
    instructions: string | null;
    is_default: boolean;
};

type Props = {
    addresses: Address[];
};

export default function AccountAddresses({ addresses }: Props) {
    const [adding, setAdding] = useState(false);

    const form = useForm({
        street: '',
        apartment: '',
        entrance: '',
        floor: '',
        intercom: '',
        instructions: '',
        is_default: addresses.length === 0,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/account/addresses', {
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    }

    function remove(id: number) {
        if (!confirm('Удалить адрес?')) return;
        router.delete(`/account/addresses/${id}`);
    }

    return (
        <>
            <Head title="Адреса" />
            <AccountLayout title="Адреса" active="addresses">
                {addresses.length === 0 && !adding ? (
                    <div className="grid place-items-center rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
                        <MapPin className="size-10 text-stone-300" />
                        <p className="mt-3 text-sm text-stone-500">Сохранённые адреса появятся здесь.</p>
                        <button
                            type="button"
                            onClick={() => setAdding(true)}
                            className="mt-4 rounded-full bg-stone-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-stone-800"
                        >
                            Добавить адрес
                        </button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {addresses.map((address) => (
                            <div
                                key={address.id}
                                className="flex items-start justify-between gap-3 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm"
                            >
                                <div>
                                    <p className="flex items-center gap-2 text-sm font-medium">
                                        {address.street}
                                        {address.is_default && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                                <Star className="size-3 fill-current" />
                                                По умолчанию
                                            </span>
                                        )}
                                    </p>
                                    <p className="mt-0.5 text-xs text-stone-500">
                                        {[
                                            address.apartment && `кв. ${address.apartment}`,
                                            address.entrance && `подъезд ${address.entrance}`,
                                            address.floor && `этаж ${address.floor}`,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                    {address.instructions && (
                                        <p className="mt-1 text-xs text-stone-500">{address.instructions}</p>
                                    )}
                                </div>
                                <button
                                    type="button"
                                    onClick={() => remove(address.id)}
                                    className="text-stone-400 hover:text-rose-600"
                                    aria-label="Удалить"
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        ))}

                        {!adding && (
                            <button
                                type="button"
                                onClick={() => setAdding(true)}
                                className="w-full rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-4 text-sm text-stone-600 hover:border-stone-400 hover:text-stone-900"
                            >
                                + Добавить новый адрес
                            </button>
                        )}
                    </div>
                )}

                {adding && (
                    <form
                        onSubmit={submit}
                        className="mt-4 space-y-3 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-base font-semibold">Новый адрес</h2>
                        <div>
                            <label className="mb-1.5 block text-xs font-medium text-stone-700">
                                Улица и дом <span className="text-rose-600">*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.street}
                                onChange={(e) => form.setData('street', e.target.value)}
                                placeholder="Тверская, 1"
                                className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                            />
                            {form.errors.street && <p className="mt-1 text-xs text-rose-600">{form.errors.street}</p>}
                        </div>

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <Field label="Кв." value={form.data.apartment} onChange={(v) => form.setData('apartment', v)} />
                            <Field label="Подъезд" value={form.data.entrance} onChange={(v) => form.setData('entrance', v)} />
                            <Field label="Этаж" value={form.data.floor} onChange={(v) => form.setData('floor', v)} />
                            <Field label="Домофон" value={form.data.intercom} onChange={(v) => form.setData('intercom', v)} />
                        </div>

                        <div>
                            <label className="mb-1.5 block text-xs font-medium text-stone-700">Инструкции курьеру</label>
                            <input
                                type="text"
                                value={form.data.instructions}
                                onChange={(e) => form.setData('instructions', e.target.value)}
                                placeholder="Позвонить за 10 минут"
                                className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_default}
                                onChange={(e) => form.setData('is_default', e.target.checked)}
                                className="size-4 accent-amber-500"
                            />
                            Сделать адресом по умолчанию
                        </label>

                        <div className="flex gap-2 pt-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 disabled:opacity-50"
                            >
                                {form.processing ? 'Сохраняем…' : 'Сохранить'}
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setAdding(false);
                                    form.reset();
                                }}
                                className="rounded-full px-5 py-2.5 text-sm text-stone-600 hover:bg-stone-100"
                            >
                                Отмена
                            </button>
                        </div>
                    </form>
                )}
            </AccountLayout>
        </>
    );
}

function Field({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) {
    return (
        <div>
            <label className="mb-1.5 block text-xs font-medium text-stone-700">{label}</label>
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
            />
        </div>
    );
}
