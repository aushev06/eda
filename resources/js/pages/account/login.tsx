import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Phone } from 'lucide-react';

type Props = {
    phone?: string;
};

export default function CustomerLogin({ phone }: Props) {
    const form = useForm({ phone: phone ?? '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/account/login/start');
    }

    return (
        <>
            <Head title="Вход" />
            <main className="grid min-h-screen place-items-center bg-stone-50 px-4">
                <div className="w-full max-w-md">
                    <Link href="/" className="mb-6 inline-flex items-center gap-2 text-sm text-stone-600 hover:text-stone-900">
                        <ArrowLeft className="size-4" />К меню
                    </Link>

                    <div className="rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
                        <div className="mb-6 grid size-12 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 text-white">
                            <Phone className="size-6" />
                        </div>
                        <h1 className="text-2xl font-semibold">Вход в кабинет</h1>
                        <p className="mt-1 text-sm text-stone-500">Введите номер — мы пришлём код подтверждения.</p>

                        <form onSubmit={submit} className="mt-6 space-y-3">
                            <div>
                                <label className="mb-1.5 block text-xs font-medium text-stone-700">
                                    Телефон <span className="text-rose-600">*</span>
                                </label>
                                <input
                                    type="tel"
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                    placeholder="+7 999 123 45 67"
                                    autoFocus
                                    className={`w-full rounded-xl border bg-white px-3 py-3 text-base text-stone-900 placeholder:text-stone-400 focus:outline-none focus:ring-2 ${
                                        form.errors.phone
                                            ? 'border-rose-300 focus:border-rose-400 focus:ring-rose-100'
                                            : 'border-stone-200 focus:border-amber-400 focus:ring-amber-100'
                                    }`}
                                />
                                {form.errors.phone && (
                                    <p className="mt-1 text-xs text-rose-600">{form.errors.phone}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing || !form.data.phone.trim()}
                                className="w-full rounded-full bg-stone-900 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-stone-800 disabled:opacity-50"
                            >
                                {form.processing ? 'Отправляем…' : 'Получить код'}
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </>
    );
}
