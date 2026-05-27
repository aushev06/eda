import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, KeyRound } from 'lucide-react';
import { useEffect, useRef } from 'react';

type Props = {
    phone?: string;
    dev_otp?: string;
};

type SharedFlash = {
    flash?: { dev_otp?: string | null };
};

export default function CustomerVerify({ phone, dev_otp }: Props) {
    const flashDev = usePage<SharedFlash>().props.flash?.dev_otp;
    const visibleDevCode = dev_otp ?? flashDev ?? null;

    const form = useForm({ phone: phone ?? '', code: '' });
    const codeInputRef = useRef<HTMLInputElement | null>(null);

    useEffect(() => {
        codeInputRef.current?.focus();
    }, []);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/account/login/verify');
    }

    if (!phone) {
        return (
            <main className="grid min-h-screen place-items-center bg-stone-50 px-4">
                <div className="text-center">
                    <p className="text-sm text-stone-600">Сначала введите телефон.</p>
                    <Link
                        href="/account/login"
                        className="mt-4 inline-flex items-center gap-2 rounded-full bg-stone-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-stone-800"
                    >
                        <ArrowLeft className="size-4" />К входу
                    </Link>
                </div>
            </main>
        );
    }

    return (
        <>
            <Head title="Подтверждение кода" />
            <main className="grid min-h-screen place-items-center bg-stone-50 px-4">
                <div className="w-full max-w-md">
                    <Link href="/account/login" className="mb-6 inline-flex items-center gap-2 text-sm text-stone-600 hover:text-stone-900">
                        <ArrowLeft className="size-4" />Изменить номер
                    </Link>

                    <div className="rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
                        <div className="mb-6 grid size-12 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white">
                            <KeyRound className="size-6" />
                        </div>
                        <h1 className="text-2xl font-semibold">Введите код</h1>
                        <p className="mt-1 text-sm text-stone-500">
                            Мы отправили 4-значный код на <span className="font-medium text-stone-900">{phone}</span>
                        </p>

                        {visibleDevCode && (
                            <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
                                <p className="font-semibold text-amber-900">DEV-режим</p>
                                <p className="text-amber-800">
                                    Код: <span className="rounded bg-white px-2 py-0.5 font-mono text-base font-bold tracking-widest">{visibleDevCode}</span>
                                </p>
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-6 space-y-3">
                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-stone-700">
                                    Код <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    ref={codeInputRef}
                                    type="text"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={form.data.code}
                                    onChange={(e) => form.setData('code', e.target.value.replace(/\D/g, ''))}
                                    placeholder="••••"
                                    className={`w-full rounded-xl border bg-white px-3 py-3 text-center text-2xl tracking-[0.5em] font-mono text-stone-900 placeholder:text-stone-300 focus:outline-none focus:ring-2 ${
                                        form.errors.code
                                            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                            : 'border-stone-200 focus:border-amber-400 focus:ring-amber-100'
                                    }`}
                                />
                                {form.errors.code && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.code}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing || form.data.code.length !== 4}
                                className="w-full rounded-full bg-stone-900 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-stone-800 disabled:opacity-50"
                            >
                                {form.processing ? 'Проверяем…' : 'Войти'}
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </>
    );
}
