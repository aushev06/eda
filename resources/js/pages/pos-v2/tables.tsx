import { router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import AddRoundedIcon from '@mui/icons-material/AddRounded';
import ScheduleRoundedIcon from '@mui/icons-material/ScheduleRounded';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import Chip from '@mui/material/Chip';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import { alpha } from '@mui/material/styles';
import Typography from '@mui/material/Typography';
import { useEffect, useState } from 'react';
import FlashSnackbar from '@/components/pos-v2/flash-snackbar';
import PosShell from '@/components/pos-v2/pos-shell';
import type { SharedPosProps } from '@/components/pos-v2/types';
import { formatPrice, minutesSince } from '@/components/pos-v2/utils';

type FloorTable = {
    id: number;
    number: number;
    label: string | null;
    check: { id: number; total: number; opened_at: string | null } | null;
};

type PageProps = SharedPosProps & {
    tables: FloorTable[];
    establishment_name: string | null;
};

/** Minutes after which an open check is highlighted as long-running. */
const LONG_CHECK_MINUTES = 90;

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <Paper
            elevation={0}
            sx={{
                px: 2.5,
                py: 1.5,
                borderRadius: 4,
                border: 1,
                borderColor: 'divider',
                minWidth: 140,
            }}
        >
            <Typography variant="caption" color="text.secondary">
                {label}
            </Typography>
            <Typography
                variant="h6"
                sx={{ fontVariantNumeric: 'tabular-nums' }}
            >
                {value}
            </Typography>
        </Paper>
    );
}

export default function PosV2Tables() {
    const { tables, establishment_name, flash } = usePage<PageProps>().props;
    const [now, setNow] = useState(() => new Date());

    // Any check change (open, add, close) refreshes the floor.
    useEcho('pos.tickets', 'PosTicketsUpdated', () =>
        router.reload({ only: ['tables'] }),
    );

    useEffect(() => {
        const ticker = setInterval(() => setNow(new Date()), 30_000);

        return () => clearInterval(ticker);
    }, []);

    const occupied = tables.filter((table) => table.check !== null);
    const openTotal = occupied.reduce(
        (sum, table) => sum + (table.check?.total ?? 0),
        0,
    );

    return (
        <PosShell title={establishment_name ?? 'Залы'} section="tables">
            <Box sx={{ flex: 1, overflowY: 'auto', p: { xs: 2, sm: 3 } }}>
                <Stack
                    direction="row"
                    sx={{ gap: 1.5, mb: 3, flexWrap: 'wrap' }}
                >
                    <Stat
                        label="Свободно"
                        value={String(tables.length - occupied.length)}
                    />
                    <Stat label="Занято" value={String(occupied.length)} />
                    <Stat
                        label="Открытые счета"
                        value={formatPrice(openTotal)}
                    />
                </Stack>

                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns:
                            'repeat(auto-fill, minmax(min(100%, 170px), 1fr))',
                    }}
                >
                    {tables.map((table) => {
                        const check = table.check;
                        const minutes = check
                            ? minutesSince(check.opened_at, now)
                            : null;
                        const long =
                            minutes !== null && minutes >= LONG_CHECK_MINUTES;

                        return (
                            <Card
                                key={table.id}
                                sx={(theme) => ({
                                    borderWidth: 2,
                                    borderColor: check
                                        ? long
                                            ? 'warning.main'
                                            : 'primary.main'
                                        : 'divider',
                                    bgcolor: check
                                        ? alpha(
                                              long
                                                  ? theme.palette.warning.main
                                                  : theme.palette.primary.main,
                                              0.07,
                                          )
                                        : 'background.paper',
                                })}
                            >
                                <CardActionArea
                                    onClick={() =>
                                        router.get(`/pos/v2/tables/${table.id}`)
                                    }
                                    sx={{
                                        height: 148,
                                        p: 2,
                                        display: 'flex',
                                        flexDirection: 'column',
                                        alignItems: 'stretch',
                                        justifyContent: 'space-between',
                                    }}
                                >
                                    <Stack
                                        direction="row"
                                        sx={{
                                            justifyContent: 'space-between',
                                            alignItems: 'flex-start',
                                            gap: 1,
                                        }}
                                    >
                                        <Typography
                                            variant="h4"
                                            sx={{
                                                fontWeight: 800,
                                                lineHeight: 1,
                                            }}
                                        >
                                            {table.label ?? table.number}
                                        </Typography>
                                        {check && minutes !== null && (
                                            <Chip
                                                size="small"
                                                icon={<ScheduleRoundedIcon />}
                                                label={`${minutes} мин`}
                                                color={
                                                    long ? 'warning' : 'primary'
                                                }
                                                variant="outlined"
                                                sx={{
                                                    bgcolor: 'background.paper',
                                                }}
                                            />
                                        )}
                                    </Stack>

                                    {check ? (
                                        <Box>
                                            <Typography
                                                variant="caption"
                                                color="text.secondary"
                                            >
                                                Счёт
                                            </Typography>
                                            <Typography
                                                variant="h6"
                                                sx={{
                                                    fontVariantNumeric:
                                                        'tabular-nums',
                                                }}
                                            >
                                                {formatPrice(check.total)}
                                            </Typography>
                                        </Box>
                                    ) : (
                                        <Stack
                                            direction="row"
                                            sx={{
                                                alignItems: 'center',
                                                gap: 0.5,
                                                color: 'text.secondary',
                                            }}
                                        >
                                            <AddRoundedIcon fontSize="small" />
                                            <Typography variant="body2">
                                                Свободен · открыть счёт
                                            </Typography>
                                        </Stack>
                                    )}
                                </CardActionArea>
                            </Card>
                        );
                    })}
                </Box>

                {tables.length === 0 && (
                    <Typography
                        color="text.secondary"
                        sx={{ textAlign: 'center', mt: 10 }}
                    >
                        Нет активных столов
                    </Typography>
                )}
            </Box>

            {flash.closed_order_number && (
                <FlashSnackbar
                    key={flash.closed_order_number}
                    message={
                        `Стол закрыт, счёт ${flash.closed_order_number} оплачен` +
                        (flash.change_due
                            ? ` · сдача ${formatPrice(flash.change_due)}`
                            : '')
                    }
                />
            )}
        </PosShell>
    );
}
