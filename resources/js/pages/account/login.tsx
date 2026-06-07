import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Phone } from 'lucide-react';
import { PhoneInput } from '@/components/forms/phone-input';
import { isValidPhone } from '@/lib/phone';

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
                            <PhoneInput
                                value={form.data.phone}
                                onChange={(v) => form.setData('phone', v)}
                                error={form.errors.phone}
                                required
                                autoFocus
                            />

                            <button
                                type="submit"
                                disabled={form.processing || !isValidPhone(form.data.phone)}
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
