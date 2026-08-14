import { Head, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect, useRef, useState } from 'react';

type KdsTicketItem = {
    id: number;
    name: string;
    quantity: number;
    modifiers: string[];
};

type KdsTicket = {
    id: number;
    number: string;
    source: 'site' | 'pos';
    source_label: string;
    delivery_type: 'delivery' | 'pickup' | 'dine_in';
    table_number: string | null;
    comment: string | null;
    age_basis: string;
    scheduled_for: string | null;
    items: KdsTicketItem[];
};

type PageProps = {
    station: 'kitchen' | 'bar';
    station_label: string;
    tickets: KdsTicket[];
    generated_at: string;
};

/** Minutes after which a ticket is visually flagged as overdue. */
const OVERDUE_MINUTES = 10;

const DELIVERY_TYPE_LABELS: Record<string, string> = {
    delivery: 'Доставка',
    pickup: 'С собой',
    dine_in: 'В зале',
};

function ageMinutes(ageBasis: string, now: Date): number {
    return Math.max(0, Math.floor((now.getTime() - new Date(ageBasis).getTime()) / 60_000));
}

function reloadTickets() {
    router.reload({ only: ['tickets', 'generated_at'] });
}

const SOUND_STORAGE_KEY = 'pos-kds-sound';

/**
 * Two-tone chime synthesized via Web Audio — no asset to load, and the
 * shared AudioContext is resumed by the sound-toggle tap, which satisfies
 * the browser autoplay policy on unattended kiosk screens.
 */
function playNewTicketChime(context: AudioContext) {
    const playTone = (frequency: number, start: number) => {
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, context.currentTime + start);
        gain.gain.exponentialRampToValueAtTime(0.4, context.currentTime + start + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + start + 0.45);
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start(context.currentTime + start);
        oscillator.stop(context.currentTime + start + 0.5);
    };

    playTone(880, 0);
    playTone(1175, 0.18);
}

export default function PosKds() {
    const { station, station_label, tickets, generated_at } = usePage<PageProps>().props;

    const [now, setNow] = useState(() => new Date());
    const [bumpingId, setBumpingId] = useState<number | null>(null);
    const [soundEnabled, setSoundEnabled] = useState(() => localStorage.getItem(SOUND_STORAGE_KEY) === 'on');

    const audioContextRef = useRef<AudioContext | null>(null);
    const knownTicketIdsRef = useRef<Set<number> | null>(null);
    const soundEnabledRef = useRef(soundEnabled);

    // Realtime push: any ticket change re-queries the list.
    useEcho('pos.tickets', 'PosTicketsUpdated', reloadTickets);

    // Safety net poll in case the WebSocket connection silently dies.
    useEffect(() => {
        const poll = setInterval(reloadTickets, 30_000);
        const ticker = setInterval(() => setNow(new Date()), 10_000);

        return () => {
            clearInterval(poll);
            clearInterval(ticker);
        };
    }, []);

    // Sound was enabled on a previous shift but the page just loaded: the
    // first tap anywhere (e.g. a bump) primes the AudioContext, since the
    // autoplay policy requires a user gesture before audio can play.
    useEffect(() => {
        const prime = () => {
            if (soundEnabledRef.current && !audioContextRef.current) {
                audioContextRef.current = new AudioContext();
                void audioContextRef.current.resume();
            }
        };

        document.addEventListener('pointerdown', prime, { once: true });

        return () => document.removeEventListener('pointerdown', prime);
    }, []);

    // Chime when a ticket id we haven't seen appears. The first render only
    // seeds the known set — screens shouldn't beep on page load.
    useEffect(() => {
        const ids = new Set(tickets.map((ticket) => ticket.id));

        if (knownTicketIdsRef.current !== null) {
            const hasNewTicket = tickets.some((ticket) => !knownTicketIdsRef.current?.has(ticket.id));

            if (hasNewTicket && soundEnabledRef.current && audioContextRef.current) {
                playNewTicketChime(audioContextRef.current);
            }
        }

        knownTicketIdsRef.current = ids;
    }, [tickets]);

    function toggleSound() {
        const next = !soundEnabled;
        setSoundEnabled(next);
        soundEnabledRef.current = next;
        localStorage.setItem(SOUND_STORAGE_KEY, next ? 'on' : 'off');

        if (next) {
            // The toggle tap is the user gesture that unlocks audio playback.
            audioContextRef.current ??= new AudioContext();
            void audioContextRef.current.resume();
            playNewTicketChime(audioContextRef.current);
        }
    }

    function bump(ticket: KdsTicket) {
        setBumpingId(ticket.id);
        router.post(
            `/pos/orders/${ticket.id}/bump/${station}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBumpingId(null),
            },
        );
    }

    return (
        <div className="flex h-screen flex-col bg-zinc-900 text-zinc-100 select-none">
            <Head title={station_label} />

            <header className="flex items-center justify-between gap-2 border-b border-zinc-700 px-3 py-2 text-sm text-zinc-400">
                <span className="text-base font-bold tracking-widest text-white uppercase">{station_label}</span>
                <div className="flex shrink-0 items-center gap-3 sm:gap-4">
                    <span>
                        Тикетов: <span className="font-bold text-white tabular-nums">{tickets.length}</span>
                    </span>
                    <span className="hidden tabular-nums sm:inline">
                        Обновлено{' '}
                        {new Date(generated_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                    </span>
                    <button
                        type="button"
                        onClick={toggleSound}
                        className={`rounded-lg border-2 px-3 py-1.5 text-sm font-semibold ${
                            soundEnabled ? 'border-emerald-500 text-emerald-400' : 'border-zinc-600 text-zinc-400'
                        }`}
                    >
                        {soundEnabled ? '🔔' : '🔕'}
                        <span className="hidden sm:inline">{soundEnabled ? ' Звук вкл' : ' Звук выкл'}</span>
                    </button>
                </div>
            </header>

            <main className="grid flex-1 auto-rows-min grid-cols-1 gap-3 overflow-y-auto p-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                {tickets.length === 0 && (
                    <p className="col-span-full mt-16 text-center text-lg text-zinc-500">Нет активных заказов</p>
                )}

                {tickets.map((ticket) => {
                    const minutes = ageMinutes(ticket.age_basis, now);
                    const overdue = minutes >= OVERDUE_MINUTES;
                    const scheduled = ticket.scheduled_for ? new Date(ticket.scheduled_for) : null;
                    const scheduledInFuture = scheduled !== null && scheduled.getTime() > now.getTime();

                    return (
                        <article
                            key={ticket.id}
                            className={`flex flex-col rounded-xl border-3 bg-zinc-800 ${
                                overdue && !scheduledInFuture ? 'animate-pulse border-red-500' : 'border-zinc-600'
                            }`}
                        >
                            <div className="flex items-baseline justify-between border-b border-zinc-600 px-3 py-2">
                                <span className="text-lg font-bold">
                                    {ticket.number}
                                    <span className="ml-2 text-xs font-normal text-zinc-400">
                                        {ticket.source_label}
                                        {ticket.table_number
                                            ? ` · стол ${ticket.table_number}`
                                            : ` · ${DELIVERY_TYPE_LABELS[ticket.delivery_type]}`}
                                    </span>
                                </span>
                                {scheduledInFuture && scheduled ? (
                                    <span className="text-sm font-bold text-sky-400">
                                        к {scheduled.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}
                                    </span>
                                ) : (
                                    <span className={`text-sm font-bold tabular-nums ${overdue ? 'text-red-400' : 'text-zinc-400'}`}>
                                        {minutes} мин
                                    </span>
                                )}
                            </div>

                            <ul className="flex-1 px-3 py-2 text-lg leading-relaxed">
                                {ticket.items.map((item) => (
                                    <li key={item.id}>
                                        <span className="font-semibold tabular-nums">{item.quantity}×</span> {item.name}
                                        {item.modifiers.length > 0 && (
                                            <div className="pl-5 text-sm leading-snug text-zinc-400">{item.modifiers.join(', ')}</div>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            {ticket.comment && (
                                <p className="border-t border-dashed border-zinc-600 px-3 py-1.5 text-sm text-amber-300">
                                    {ticket.comment}
                                </p>
                            )}

                            <button
                                type="button"
                                onClick={() => bump(ticket)}
                                disabled={bumpingId === ticket.id}
                                className="m-2.5 h-16 rounded-lg border-3 border-emerald-500 text-lg font-extrabold text-emerald-400 transition-colors active:bg-emerald-500 active:text-zinc-900 disabled:opacity-40"
                            >
                                ГОТОВО
                            </button>
                        </article>
                    );
                })}
            </main>
        </div>
    );
}
