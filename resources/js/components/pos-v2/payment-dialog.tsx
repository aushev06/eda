import BackspaceOutlinedIcon from '@mui/icons-material/BackspaceOutlined';
import CreditCardRoundedIcon from '@mui/icons-material/CreditCardRounded';
import PaymentsRoundedIcon from '@mui/icons-material/PaymentsRounded';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import ButtonBase from '@mui/material/ButtonBase';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogContent from '@mui/material/DialogContent';
import Stack from '@mui/material/Stack';
import { alpha, useTheme } from '@mui/material/styles';
import Typography from '@mui/material/Typography';
import useMediaQuery from '@mui/material/useMediaQuery';
import type { ReactNode } from 'react';
import { useState } from 'react';
import type { Tender } from './types';
import { formatPrice, round2 } from './utils';

type Props = {
    total: number;
    title?: string;
    busy?: boolean;
    onSettle: (tenders: Tender[]) => void;
    onCancel: () => void;
};

const BANKNOTES = [500, 1000, 2000, 5000];

function AmountField({
    label,
    icon,
    value,
    active,
    disabled,
    onClick,
}: {
    label: string;
    icon: ReactNode;
    value: string;
    active: boolean;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <ButtonBase
            onClick={onClick}
            disabled={disabled}
            sx={(theme) => ({
                flex: 1,
                p: 1.5,
                borderRadius: 3,
                border: 2,
                justifyContent: 'flex-start',
                textAlign: 'left',
                borderColor: active ? 'primary.main' : 'divider',
                bgcolor: active
                    ? alpha(theme.palette.primary.main, 0.06)
                    : 'background.paper',
                opacity: disabled ? 0.45 : 1,
            })}
        >
            <Stack spacing={0.5} sx={{ width: '100%' }}>
                <Stack
                    direction="row"
                    sx={{
                        alignItems: 'center',
                        gap: 0.75,
                        color: active ? 'primary.main' : 'text.secondary',
                    }}
                >
                    {icon}
                    <Typography variant="caption" sx={{ fontWeight: 600 }}>
                        {label}
                    </Typography>
                </Stack>
                <Typography
                    variant="h5"
                    sx={{ fontVariantNumeric: 'tabular-nums' }}
                >
                    {value}
                </Typography>
            </Stack>
        </ButtonBase>
    );
}

/**
 * Split-tender payment pad. The cashier enters how much goes on card (exact,
 * part of the bill) and how much cash the guest handed over:
 *  - card is capped at the bill;
 *  - when card covers the whole bill the cash field is locked;
 *  - "Оплатить" stays disabled until the money covers the bill;
 *  - change shows only when cash is genuinely overpaid.
 */
export default function PaymentDialog({
    total,
    title = 'К оплате',
    busy,
    onSettle,
    onCancel,
}: Props) {
    const theme = useTheme();
    const fullScreen = useMediaQuery(theme.breakpoints.down('sm'));

    const [active, setActive] = useState<'cash' | 'card'>('cash');
    const [cardStr, setCardStr] = useState('');
    const [cashStr, setCashStr] = useState('');

    const cardRaw = Math.max(0, parseFloat(cardStr) || 0);
    const card = Math.min(cardRaw, total);
    const cardOver = cardRaw > total + 0.001;
    const cashDue = round2(total - card);
    const cardCoversAll = cashDue <= 0.001;
    const cashReceived = cardCoversAll
        ? 0
        : Math.max(0, parseFloat(cashStr) || 0);
    const remaining = Math.max(0, round2(cashDue - cashReceived));
    const change =
        cashDue > 0 ? Math.max(0, round2(cashReceived - cashDue)) : 0;
    const covered = remaining <= 0.001;

    const activeField: 'cash' | 'card' = cardCoversAll ? 'card' : active;
    const current = activeField === 'cash' ? cashStr : cardStr;
    const setCurrent = activeField === 'cash' ? setCashStr : setCardStr;

    function tapDigit(digit: string) {
        if (digit === '.' && current.includes('.')) {
            return;
        }

        if (
            current.includes('.') &&
            (current.split('.')[1]?.length ?? 0) >= 2 &&
            digit !== '.'
        ) {
            return;
        }

        setCurrent((current + digit).replace(/^0(\d)/, '$1'));
    }

    function payExact(method: 'cash' | 'card') {
        setCardStr(method === 'card' ? String(total) : '');
        setCashStr(method === 'cash' ? String(total) : '');
        setActive(method);
    }

    function settle() {
        if (!covered || busy) {
            return;
        }

        const tenders: Tender[] = [];

        if (card > 0) {
            tenders.push({ method: 'card_online', amount: round2(card) });
        }

        if (cashDue > 0) {
            tenders.push({
                method: 'cash',
                amount: cashDue,
                received_amount: round2(cashReceived),
            });
        }

        if (tenders.length > 0) {
            onSettle(tenders);
        }
    }

    const banknotes = BANKNOTES.filter((note) => note >= cashDue).slice(0, 3);

    return (
        <Dialog
            open
            onClose={onCancel}
            fullScreen={fullScreen}
            fullWidth
            maxWidth="xs"
        >
            <DialogContent
                sx={{
                    p: 2.5,
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 2,
                }}
            >
                <Stack
                    direction="row"
                    sx={{
                        alignItems: 'baseline',
                        justifyContent: 'space-between',
                    }}
                >
                    <Typography variant="h6">{title}</Typography>
                    <Typography
                        variant="h4"
                        sx={{
                            fontWeight: 800,
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {formatPrice(total)}
                    </Typography>
                </Stack>

                <Stack direction="row" spacing={1.5}>
                    <AmountField
                        label="Наличными (получено)"
                        icon={<PaymentsRoundedIcon fontSize="small" />}
                        value={cardCoversAll ? '—' : cashStr || '0'}
                        active={activeField === 'cash'}
                        disabled={cardCoversAll}
                        onClick={() => setActive('cash')}
                    />
                    <AmountField
                        label="Картой"
                        icon={<CreditCardRoundedIcon fontSize="small" />}
                        value={cardStr || '0'}
                        active={activeField === 'card'}
                        onClick={() => setActive('card')}
                    />
                </Stack>

                {cardOver && (
                    <Alert severity="warning">
                        Картой нельзя больше суммы заказа — учтём{' '}
                        {formatPrice(total)}.
                    </Alert>
                )}
                {cardCoversAll && card > 0 && (
                    <Alert severity="info">
                        Карта покрывает весь счёт — наличные не нужны.
                    </Alert>
                )}

                <Stack direction="row" spacing={1.5}>
                    <Box
                        sx={{
                            flex: 1,
                            p: 1.5,
                            borderRadius: 3,
                            bgcolor:
                                remaining > 0
                                    ? alpha(theme.palette.error.main, 0.08)
                                    : 'action.hover',
                        }}
                    >
                        <Typography variant="caption" color="text.secondary">
                            {remaining > 0
                                ? 'Не хватает'
                                : 'Наличными к оплате'}
                        </Typography>
                        <Typography
                            variant="h6"
                            color={remaining > 0 ? 'error' : 'text.primary'}
                            sx={{ fontVariantNumeric: 'tabular-nums' }}
                        >
                            {formatPrice(remaining > 0 ? remaining : cashDue)}
                        </Typography>
                    </Box>
                    <Box
                        sx={{
                            flex: 1,
                            p: 1.5,
                            borderRadius: 3,
                            bgcolor:
                                change > 0
                                    ? alpha(theme.palette.success.main, 0.1)
                                    : 'action.hover',
                        }}
                    >
                        <Typography variant="caption" color="text.secondary">
                            Сдача
                        </Typography>
                        <Typography
                            variant="h6"
                            color={change > 0 ? 'success.main' : 'text.primary'}
                            sx={{ fontVariantNumeric: 'tabular-nums' }}
                        >
                            {formatPrice(change)}
                        </Typography>
                    </Box>
                </Stack>

                <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1 }}>
                    <Chip
                        clickable
                        label="Без сдачи"
                        icon={<PaymentsRoundedIcon />}
                        onClick={() => payExact('cash')}
                    />
                    <Chip
                        clickable
                        label="Всё картой"
                        icon={<CreditCardRoundedIcon />}
                        onClick={() => payExact('card')}
                    />
                    {!cardCoversAll &&
                        banknotes.map((note) => (
                            <Chip
                                key={note}
                                clickable
                                variant="outlined"
                                label={formatPrice(note)}
                                onClick={() => {
                                    setCashStr(String(note));
                                    setActive('cash');
                                }}
                            />
                        ))}
                </Stack>

                <Box
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(3, 1fr)',
                        gap: 1,
                    }}
                >
                    {[
                        '1',
                        '2',
                        '3',
                        '4',
                        '5',
                        '6',
                        '7',
                        '8',
                        '9',
                        '.',
                        '0',
                        '⌫',
                    ].map((key) => (
                        <Button
                            key={key}
                            variant="outlined"
                            color="inherit"
                            onClick={() =>
                                key === '⌫'
                                    ? setCurrent(current.slice(0, -1))
                                    : tapDigit(key)
                            }
                            sx={{
                                height: 58,
                                fontSize: '1.4rem',
                                fontWeight: 600,
                                borderColor: 'divider',
                            }}
                            aria-label={key === '⌫' ? 'Стереть' : key}
                        >
                            {key === '⌫' ? <BackspaceOutlinedIcon /> : key}
                        </Button>
                    ))}
                </Box>

                <Stack direction="row" spacing={1}>
                    <Button
                        onClick={onCancel}
                        size="large"
                        color="inherit"
                        sx={{ flex: 1 }}
                    >
                        Отмена
                    </Button>
                    <Button
                        onClick={() => setCurrent('')}
                        size="large"
                        color="inherit"
                        variant="outlined"
                        sx={{ borderColor: 'divider' }}
                    >
                        Сброс
                    </Button>
                    <Button
                        onClick={settle}
                        disabled={!covered || busy}
                        size="large"
                        variant="contained"
                        color="success"
                        sx={{ flex: 1.6 }}
                    >
                        {busy ? 'Оплата…' : 'Оплатить'}
                    </Button>
                </Stack>
            </DialogContent>
        </Dialog>
    );
}
