import { Head, useForm } from '@inertiajs/react';
import { AccountLayout } from '@/components/account/account-layout';

type Customer = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
};

type Props = {
    customer: Customer;
};

export default function AccountProfile({ customer }: Props) {
    const form = useForm({
        name: customer.name,
        email: customer.email ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.patch('/account');
    }

    return (
        <>
            <Head title="Профиль" />
            <AccountLayout title="Профиль" active="profile">
                <form
                    onSubmit={submit}
                    className="space-y-4 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm"
                >
                    <div>
                        <label className="mb-1.5 block text-xs font-medium text-stone-700">
                            Имя <span className="text-rose-600">*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                        />
                        {form.errors.name && <p className="mt-1 text-xs text-rose-600">{form.errors.name}</p>}
                    </div>

                    <div>
                        <label className="mb-1.5 block text-xs font-medium text-stone-700">Email</label>
                        <input
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            className="w-full rounded-xl border border-stone-200 px-3 py-2.5 text-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
                        />
                        {form.errors.email && <p className="mt-1 text-xs text-rose-600">{form.errors.email}</p>}
                    </div>

                    <div>
                        <label className="mb-1.5 block text-xs font-medium text-stone-700">Телефон</label>
                        <input
                            type="tel"
                            value={customer.phone}
                            disabled
                            className="w-full rounded-xl border border-stone-200 bg-stone-50 px-3 py-2.5 text-sm text-stone-500"
                        />
                        <p className="mt-1 text-xs text-stone-400">
                            Телефон изменить нельзя — он используется как ваш идентификатор.
                        </p>
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-full bg-stone-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-stone-800 disabled:opacity-50"
                    >
                        {form.processing ? 'Сохраняем…' : 'Сохранить'}
                    </button>
                </form>
            </AccountLayout>
        </>
    );
}
