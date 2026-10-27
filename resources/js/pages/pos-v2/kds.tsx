import { router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import CheckRoundedIcon from '@mui/icons-material/CheckRounded';
import EventRoundedIcon from '@mui/icons-material/EventRounded';
import NotificationsActiveRoundedIcon from '@mui/icons-material/NotificationsActiveRounded';
import NotificationsOffOutlinedIcon from '@mui/icons-material/NotificationsOffOutlined';
import StickyNote2OutlinedIcon from '@mui/icons-material/StickyNote2Outlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import LinearProgress from '@mui/material/LinearProgress';
import Stack from '@mui/material/Stack';
import { alpha, keyframes } from '@mui/material/styles';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import { useEffect, useRef, useState } from 'react';
import PosShell from '@/components/pos-v2/pos-shell';
import { kdsTheme } from '@/components/pos-v2/theme';

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

/** Minutes after which a ticket is flagged as overdue. */
const OVERDUE_MINUTES = 10;

const SOUND_STORAGE_KEY = 'pos-kds-sound';

const DELIVERY_TYPE_LABELS: Record<string, string> = {
    delivery: 'Доставка',
    pickup: 'С собой',
    dine_in: 'В зале',
};

const overdueGlow = keyframes`
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.0); }
    50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.35); }
`;

function ageMinutes(ageBasis: string, now: Date): number {
    return Math.max(
        0,
        Math.floor((now.getTime() - new Date(ageBasis).getTime()) / 60_000),
    );
}

function reloadTickets() {
    router.reload({ only: ['tickets', 'generated_at'] });
}

function readSoundPreference(): boolean {
    try {
        return localStorage.getItem(SOUND_STORAGE_KEY) === 'on';
    } catch {
        return false;
    }
}

/** Two-tone chime synthesized via Web Audio — no asset to load. */
function playNewTicketChime(context: AudioContext) {
    const playTone = (frequency: number, start: number) => {
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, context.currentTime + start);
        gain.gain.exponentialRampToValueAtTime(
            0.4,
            context.currentTime + start + 0.02,
        );
        gain.gain.exponentialRampToValueAtTime(
            0.0001,
            context.currentTime + start + 0.45,
        );
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start(context.currentTime + start);
        oscillator.stop(context.currentTime + start + 0.5);
    };

    playTone(880, 0);
    playTone(1175, 0.18);
}

function TicketCard({
    ticket,
    now,
    bumping,
    onBump,
}: {
    ticket: KdsTicket;
    now: Date;
    bumping: boolean;
    onBump: () => void;
}) {
    const minutes = ageMinutes(ticket.age_basis, now);
    const scheduled = ticket.scheduled_for
        ? new Date(ticket.scheduled_for)
        : null;
    const scheduledInFuture =
        scheduled !== null && scheduled.getTime() > now.getTime();
    const overdue = minutes >= OVERDUE_MINUTES && !scheduledInFuture;
    const warning =
        !overdue && !scheduledInFuture && minutes >= OVERDUE_MINUTES * 0.6;
    const tone = overdue ? 'error' : warning ? 'warning' : 'success';

    return (
        <Card
            sx={(theme) => ({
                display: 'flex',
                flexDirection: 'column',
                borderWidth: 2,
                borderColor: overdue ? 'error.main' : 'divider',
                bgcolor: 'background.paper',
                animation: overdue
                    ? `${overdueGlow} 1.6s ease-in-out infinite`
                    : undefined,
                '& .ticket-head': {
                    bgcolor: alpha(theme.palette[tone].main, 0.14),
                },
            })}
        >
            <Box className="ticket-head" sx={{ px: 2, pt: 1.5, pb: 1.25 }}>
                <Stack
                    direction="row"
                    sx={{
                        justifyContent: 'space-between',
                        alignItems: 'baseline',
                        gap: 1,
                    }}
                >
                    <Typography
                        variant="h5"
                        sx={{ fontWeight: 800, letterSpacing: 0.5 }}
                    >
                        {ticket.number}
                    </Typography>
                    {scheduledInFuture && scheduled ? (
                        <Chip
                            size="small"
                            color="info"
                            icon={<EventRoundedIcon />}
                            label={`к ${scheduled.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}`}
                        />
                    ) : (
                        <Typography
                            variant="h6"
                            color={`${tone}.main`}
                            sx={{ fontVariantNumeric: 'tabular-nums' }}
                        >
                            {minutes} мин
                        </Typography>
                    )}
                </Stack>
                <Typography variant="body2" color="text.secondary">
                    {ticket.source_label} ·{' '}
                    {ticket.table_number
                        ? `стол ${ticket.table_number}`
                        : DELIVERY_TYPE_LABELS[ticket.delivery_type]}
                </Typography>
                {!scheduledInFuture && (
                    <LinearProgress
                        variant="determinate"
                        color={tone}
                        value={Math.min(100, (minutes / OVERDUE_MINUTES) * 100)}
                        sx={{
                            mt: 1.25,
                            height: 5,
                            borderRadius: 3,
                            bgcolor: 'rgba(255,255,255,0.08)',
                        }}
                    />
                )}
            </Box>

            <Stack
                component="ul"
                sx={{
                    listStyle: 'none',
                    m: 0,
                    px: 2,
                    py: 1.5,
                    gap: 1.25,
                    flex: 1,
                }}
            >
                {ticket.items.map((item) => (
                    <Box component="li" key={item.id}>
                        <Typography
                            sx={{
                                fontSize: '1.2rem',
                                fontWeight: 600,
                                lineHeight: 1.3,
                            }}
                        >
                            <Box
                                component="span"
                                sx={{
                                    color: 'primary.main',
                                    fontVariantNumeric: 'tabular-nums',
                                    mr: 1,
                                }}
                            >
                                {item.quantity}×
                            </Box>
                            {item.name}
                        </Typography>
                        {item.modifiers.length > 0 && (
                            <Typography
                                variant="body2"
                                color="text.secondary"
                                sx={{ pl: 4 }}
                            >
                                {item.modifiers.join(', ')}
                            </Typography>
                        )}
                    </Box>
                ))}
            </Stack>

            {ticket.comment && (
                <Stack
                    direction="row"
                    sx={{
                        mx: 2,
                        mb: 1.5,
                        p: 1.25,
                        gap: 1,
                        borderRadius: 2,
                        bgcolor: alpha('#FBBF24', 0.12),
                        color: 'secondary.main',
                    }}
                >
                    <StickyNote2OutlinedIcon fontSize="small" />
                    <Typography variant="body2">{ticket.comment}</Typography>
                </Stack>
            )}

            <Box sx={{ p: 1.5, pt: 0 }}>
                <Button
                    fullWidth
                    size="large"
                    variant="contained"
                    color="success"
                    startIcon={<CheckRoundedIcon />}
                    onClick={onBump}
                    disabled={bumping}
                    sx={{ minHeight: 64, fontSize: '1.15rem', fontWeight: 800 }}
                >
                    Готово
                </Button>
            </Box>
        </Card>
    );
}

export default function PosV2Kds() {
    const { station, station_label, tickets, generated_at } =
        usePage<PageProps>().props;

    const [now, setNow] = useState(() => new Date());
    const [bumpingId, setBumpingId] = useState<number | null>(null);
    const [soundEnabled, setSoundEnabled] = useState(readSoundPreference);

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

    // The autoplay policy needs a user gesture: the first tap primes audio
    // when sound was left on from a previous shift.
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

    // Chime when an unseen ticket appears; the first render only seeds the set.
    useEffect(() => {
        const ids = new Set(tickets.map((ticket) => ticket.id));

        if (knownTicketIdsRef.current !== null) {
            const hasNewTicket = tickets.some(
                (ticket) => !knownTicketIdsRef.current?.has(ticket.id),
            );

            if (
                hasNewTicket &&
                soundEnabledRef.current &&
                audioContextRef.current
            ) {
                playNewTicketChime(audioContextRef.current);
            }
        }

        knownTicketIdsRef.current = ids;
    }, [tickets]);

    function toggleSound() {
        const next = !soundEnabled;
        setSoundEnabled(next);
        soundEnabledRef.current = next;

        try {
            localStorage.setItem(SOUND_STORAGE_KEY, next ? 'on' : 'off');
        } catch {
            // Kiosk browsers may block storage; the toggle still works for this session.
        }

        if (next) {
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
            { preserveScroll: true, onFinish: () => setBumpingId(null) },
        );
    }

    const overdueCount = tickets.filter((ticket) => {
        const future =
            ticket.scheduled_for &&
            new Date(ticket.scheduled_for).getTime() > now.getTime();

        return !future && ageMinutes(ticket.age_basis, now) >= OVERDUE_MINUTES;
    }).length;

    const actions = (
        <Stack direction="row" sx={{ alignItems: 'center', gap: 1 }}>
            <Chip
                label={`В работе: ${tickets.length}`}
                color="primary"
                variant="outlined"
            />
            {overdueCount > 0 && (
                <Chip label={`Просрочено: ${overdueCount}`} color="error" />
            )}
            <Typography
                variant="caption"
                color="text.secondary"
                sx={{
                    display: { xs: 'none', lg: 'block' },
                    fontVariantNumeric: 'tabular-nums',
                }}
            >
                обновлено{' '}
                {new Date(generated_at).toLocaleTimeString('ru-RU', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                })}
            </Typography>
            <Tooltip title={soundEnabled ? 'Звук включён' : 'Звук выключен'}>
                <IconButton
                    onClick={toggleSound}
                    color={soundEnabled ? 'success' : 'default'}
                    aria-label="Звук новых заказов"
                >
                    {soundEnabled ? (
                        <NotificationsActiveRoundedIcon />
                    ) : (
                        <NotificationsOffOutlinedIcon />
                    )}
                </IconButton>
            </Tooltip>
        </Stack>
    );

    return (
        <PosShell
            title={station_label}
            section={station}
            theme={kdsTheme}
            actions={actions}
        >
            <Box
                sx={{
                    flex: 1,
                    overflowY: 'auto',
                    p: 2,
                    display: 'grid',
                    gap: 2,
                    alignContent: 'start',
                    gridTemplateColumns:
                        'repeat(auto-fill, minmax(min(100%, 300px), 1fr))',
                }}
            >
                {tickets.map((ticket) => (
                    <TicketCard
                        key={ticket.id}
                        ticket={ticket}
                        now={now}
                        bumping={bumpingId === ticket.id}
                        onBump={() => bump(ticket)}
                    />
                ))}
                {tickets.length === 0 && (
                    <Stack
                        sx={{
                            gridColumn: '1 / -1',
                            alignItems: 'center',
                            mt: 12,
                            gap: 1,
                            color: 'text.secondary',
                        }}
                    >
                        <CheckRoundedIcon sx={{ fontSize: 56, opacity: 0.4 }} />
                        <Typography variant="h6">Все заказы готовы</Typography>
                    </Stack>
                )}
            </Box>
        </PosShell>
    );
}
