import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Crown, Gem, Gift, Sparkles, Star } from 'lucide-react';
import { formatRub } from '@/lib/format';

type Level = {
    id: number;
    name: string;
    min_lifetime_spend: string;
    cashback_percent: string;
};

type Establishment = {
    name: string;
    phone: string;
    accepting_orders: boolean;
    closed_reason: string | null;
};

type Props = {
    establishment: Establishment;
    levels: Level[];
    is_enabled: boolean;
    max_spend_percent: number;
    customer_balance: number | null;
};

// Icon used in level cards, escalates with tier.
const TIER_ICONS = [Sparkles, Star, Gem, Crown];

const TIER_GRADIENTS = [
    'from-amber-400 to-orange-500',
    'from-sky-400 to-blue-500',
    'from-violet-400 to-purple-500',
    'from-yellow-400 via-orange-500 to-rose-500',
];

export default function LoyaltyInfo({ establishment, levels, is_enabled, max_spend_percent, customer_balance }: Props) {
    const isAuthed = customer_balance !== null;

    return (
        <>
            <Head title="Программа лояльности" />
            <div className="min-h-screen bg-stone-50 text-stone-900">
                <header className="border-b border-stone-200 bg-white">
                    <div className="mx-auto flex h-16 max-w-5xl items-center justify-between gap-4 px-4 sm:px-6">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-2 text-sm text-stone-600 hover:text-stone-900"
                        >
                            <ArrowLeft className="size-4" />К меню
                        </Link>
                        <span className="text-base font-semibold">{establishment.name}</span>
                    </div>
                </header>

                <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                    {/* Hero */}
                    <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-400 via-orange-500 to-rose-500 p-8 text-white shadow-lg sm:p-12">
                        <div className="absolute -right-12 -top-12 size-56 rounded-full bg-white/15 blur-3xl" />
                        <div className="absolute -bottom-16 -left-8 size-64 rounded-full bg-white/10 blur-3xl" />

                        <div className="relative">
                            <div className="mb-5 inline-flex size-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                                <Gift className="size-7" />
                            </div>
                            <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                Бонусная программа {establishment.name}
                            </h1>
                            <p className="mt-3 max-w-xl text-base text-amber-50/95">
                                Виртуальная валюта со скидкой при заказе. Бонусы начисляются с каждого заказа и копятся
                                на вашем счёте. <span className="font-semibold">1 бонус = 1 рубль.</span>
                            </p>

                            <div className="mt-6 flex flex-wrap items-center gap-3">
                                {isAuthed ? (
                                    <>
                                        <div className="rounded-2xl bg-white/15 px-4 py-3 backdrop-blur-sm">
                                            <p className="text-xs text-amber-50/80">Ваш баланс</p>
                                            <p className="text-2xl font-bold">{formatBonuses(customer_balance ?? 0)}</p>
                                        </div>
                                        <Link
                                            href="/account/bonuses"
                                            className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-stone-900 hover:bg-stone-100"
                                        >
                                            Мой бонусный счёт <ArrowRight className="size-4" />
                                        </Link>
                                    </>
                                ) : (
                                    <Link
                                        href="/account/login"
                                        className="inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-stone-900 hover:bg-stone-100"
                                    >
                                        Войти в личный кабинет <ArrowRight className="size-4" />
                                    </Link>
                                )}
                            </div>
                        </div>
                    </section>

                    {!is_enabled && (
                        <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                            <p className="font-semibold">Программа сейчас приостановлена</p>
                            <p className="mt-1">Списать бонусы временно нельзя. Начисление продолжается — бонусы не сгорают.</p>
                        </div>
                    )}

                    {/* Levels */}
                    <section className="mt-10">
                        <h2 className="text-2xl font-bold tracking-tight">Уровни программы</h2>
                        <p className="mt-1 text-sm text-stone-500">
                            Чем больше вы заказываете, тем выше уровень и процент кэшбека.
                        </p>

                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            {levels.map((level, index) => {
                                const Icon = TIER_ICONS[index] ?? Star;
                                const gradient = TIER_GRADIENTS[index] ?? 'from-stone-400 to-stone-500';
                                const threshold = Number(level.min_lifetime_spend);
                                const percent = Number(level.cashback_percent);
                                return (
                                    <div
                                        key={level.id}
                                        className="overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                                    >
                                        <div className={`bg-gradient-to-br ${gradient} px-5 py-4 text-white`}>
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <Icon className="size-6" />
                                                    <span className="text-lg font-semibold">{level.name}</span>
                                                </div>
                                                <span className="rounded-full bg-white/20 px-3 py-1 text-sm font-bold backdrop-blur-sm">
                                                    {formatPercent(percent)}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="px-5 py-4">
                                            <p className="text-sm text-stone-600">
                                                {threshold === 0 ? (
                                                    <>Действует со <span className="font-semibold text-stone-900">первого заказа</span> на ваш номер телефона.</>
                                                ) : (
                                                    <>
                                                        При сумме заказов от{' '}
                                                        <span className="font-semibold text-stone-900">{formatRub(threshold)}</span>
                                                    </>
                                                )}
                                            </p>
                                            <p className="mt-2 text-sm text-stone-500">
                                                Бонусы начисляются в размере{' '}
                                                <span className="font-semibold text-stone-700">{formatPercent(percent)}</span> от
                                                стоимости заказа.
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    {/* How it works */}
                    <section className="mt-10 grid gap-4 sm:grid-cols-3">
                        <InfoCard
                            icon={<Gift className="size-5" />}
                            title="Как начисляются"
                            text="Заказывайте через сайт, в приложении или по телефону. После доставки бонусы автоматически появятся на вашем счёте."
                        />
                        <InfoCard
                            icon={<Sparkles className="size-5" />}
                            title="Сколько можно списать"
                            text={`До ${max_spend_percent}% стоимости заказа можно оплатить бонусами. 1 бонус = 1 рубль.`}
                        />
                        <InfoCard
                            icon={<Crown className="size-5" />}
                            title="Как растёт уровень"
                            text="Уровень определяется суммой всех ваших заказов. Чем больше оборот — тем выше уровень и процент кэшбека."
                        />
                    </section>

                    {/* FAQ */}
                    <section className="mt-10 rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                        <h2 className="text-xl font-bold tracking-tight">Частые вопросы</h2>
                        <dl className="mt-5 space-y-5">
                            <FaqItem question="Как привязать счёт к моему номеру телефона?">
                                Войдите в личный кабинет по своему номеру — мы пришлём код подтверждения. Все ваши прошлые
                                и будущие заказы автоматически связываются с этим номером.
                            </FaqItem>
                            <FaqItem question="Бонусы сгорают?">
                                Нет, бонусы не сгорают и не имеют срока действия. Они продолжают копиться, пока вы
                                заказываете.
                            </FaqItem>
                            <FaqItem question="Что если заказ отменили?">
                                Списанные бонусы вернутся на счёт, начисленный кэшбек будет аннулирован. Оборот заказов также
                                корректируется, чтобы отменённый заказ не влиял на уровень.
                            </FaqItem>
                            <FaqItem question="Где посмотреть свой баланс?">
                                В разделе <Link href="/account/bonuses" className="font-semibold text-stone-900 underline">Бонусы</Link>{' '}
                                личного кабинета. Там же — текущий уровень, прогресс к следующему и история операций.
                            </FaqItem>
                        </dl>
                    </section>

                    {!isAuthed && (
                        <div className="mt-8 rounded-2xl bg-stone-900 p-6 text-white sm:p-8">
                            <h3 className="text-xl font-semibold">Начните копить бонусы прямо сейчас</h3>
                            <p className="mt-1 text-sm text-stone-300">
                                Войдите по телефону и оформите заказ. Бонусы начислятся автоматически.
                            </p>
                            <Link
                                href="/account/login"
                                className="mt-4 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-stone-900 hover:bg-stone-100"
                            >
                                Войти <ArrowRight className="size-4" />
                            </Link>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}

function InfoCard({ icon, title, text }: { icon: React.ReactNode; title: string; text: string }) {
    return (
        <div className="rounded-2xl border border-stone-200 bg-white p-5 shadow-sm">
            <div className="mb-2 inline-flex size-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                {icon}
            </div>
            <h3 className="text-base font-semibold">{title}</h3>
            <p className="mt-1 text-sm text-stone-600">{text}</p>
        </div>
    );
}

function FaqItem({ question, children }: { question: string; children: React.ReactNode }) {
    return (
        <div>
            <dt className="text-sm font-semibold text-stone-900">{question}</dt>
            <dd className="mt-1 text-sm text-stone-600">{children}</dd>
        </div>
    );
}

function formatPercent(value: number): string {
    const formatted = Number.isInteger(value) ? String(value) : value.toFixed(1).replace(/\.0$/, '');
    return `${formatted}%`;
}

function formatBonuses(value: number): string {
    return `${new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(value)} б.`;
}
