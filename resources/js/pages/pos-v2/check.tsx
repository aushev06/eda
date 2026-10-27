import { router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import ArrowBackRoundedIcon from '@mui/icons-material/ArrowBackRounded';
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded';
import DeleteOutlineRoundedIcon from '@mui/icons-material/DeleteOutlineRounded';
import HourglassTopRoundedIcon from '@mui/icons-material/HourglassTopRounded';
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined';
import SendRoundedIcon from '@mui/icons-material/SendRounded';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import { alpha } from '@mui/material/styles';
import TextField from '@mui/material/TextField';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import DraftLineItem from '@/components/pos-v2/draft-line-item';
import ModifierDialog from '@/components/pos-v2/modifier-dialog';
import OrderPanel from '@/components/pos-v2/order-panel';
import PaymentDialog from '@/components/pos-v2/payment-dialog';
import PosShell from '@/components/pos-v2/pos-shell';
import ProductCatalog from '@/components/pos-v2/product-catalog';
import type {
    DraftLine,
    PosCategory,
    PosProduct,
    Station,
    Tender,
} from '@/components/pos-v2/types';
import {
    addToLines,
    changeLineQuantity,
    draftPayload,
    formatPrice,
    lineTotal,
    STATION_LABELS,
} from '@/components/pos-v2/utils';

type CheckItem = {
    id: number;
    name: string;
    quantity: number;
    line_total: number;
    station: Station | null;
    completed: boolean;
    voided: boolean;
    void_reason: string | null;
    modifiers: { name: string; price_delta: number }[];
};

type Check = {
    id: number;
    number: string;
    total: number;
    opened_at: string | null;
    items: CheckItem[];
};

type PageProps = {
    table: { id: number; number: number; label: string | null };
    categories: PosCategory[];
    check: Check;
};

const VOID_REASONS = [
    'Гость передумал',
    'Ошибка кассира',
    'Брак',
    'Долго готовилось',
];

function CheckItemRow({
    item,
    busy,
    onRemove,
}: {
    item: CheckItem;
    busy: boolean;
    onRemove: () => void;
}) {
    return (
        <Stack
            direction="row"
            sx={{
                py: 1.5,
                gap: 1,
                borderBottom: 1,
                borderColor: 'divider',
                alignItems: 'flex-start',
            }}
        >
            <Box sx={{ minWidth: 0, flex: 1 }}>
                <Typography variant="subtitle1" sx={{ lineHeight: 1.25 }}>
                    <Box
                        component="span"
                        sx={{
                            color: 'text.secondary',
                            fontVariantNumeric: 'tabular-nums',
                        }}
                    >
                        {item.quantity}×
                    </Box>{' '}
                    {item.name}
                </Typography>
                {item.modifiers.length > 0 && (
                    <Typography
                        variant="caption"
                        color="text.secondary"
                        component="div"
                    >
                        {item.modifiers
                            .map((modifier) => modifier.name)
                            .join(', ')}
                    </Typography>
                )}
                <Stack direction="row" sx={{ gap: 0.75, mt: 0.75 }}>
                    <Chip
                        size="small"
                        icon={
                            item.completed ? (
                                <CheckCircleRoundedIcon />
                            ) : (
                                <HourglassTopRoundedIcon />
                            )
                        }
                        label={item.completed ? 'Готово' : 'Готовится'}
                        color={item.completed ? 'success' : 'warning'}
                        variant="outlined"
                    />
                    {item.station && (
                        <Chip
                            size="small"
                            label={STATION_LABELS[item.station]}
                            variant="outlined"
                        />
                    )}
                </Stack>
            </Box>
            <Stack sx={{ alignItems: 'flex-end' }}>
                <Typography
                    variant="subtitle1"
                    sx={{ fontVariantNumeric: 'tabular-nums' }}
                >
                    {formatPrice(item.line_total)}
                </Typography>
                <Tooltip title={item.completed ? 'Списать' : 'Убрать'}>
                    <span>
                        <IconButton
                            onClick={onRemove}
                            disabled={busy}
                            color="error"
                            aria-label={item.completed ? 'Списать' : 'Убрать'}
                        >
                            <DeleteOutlineRoundedIcon />
                        </IconButton>
                    </span>
                </Tooltip>
            </Stack>
        </Stack>
    );
}

export default function PosV2Check() {
    const { table, categories, check } = usePage<PageProps>().props;

    const [draft, setDraft] = useState<DraftLine[]>([]);
    const [modifierTarget, setModifierTarget] = useState<PosProduct | null>(
        null,
    );
    const [voidTarget, setVoidTarget] = useState<CheckItem | null>(null);
    const [voidReason, setVoidReason] = useState('');
    const [paying, setPaying] = useState(false);
    const [busy, setBusy] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);

    // KDS bumps and other cashiers' edits refresh the live check.
    useEcho('pos.tickets', 'PosTicketsUpdated', () =>
        router.reload({ only: ['check'] }),
    );

    const tableName = table.label ?? `Стол ${table.number}`;
    const liveItems = check.items.filter((item) => !item.voided);
    const draftTotal = draft.reduce((sum, line) => sum + lineTotal(line), 0);
    const draftCount = draft.reduce((sum, line) => sum + line.quantity, 0);

    function pickProduct(product: PosProduct) {
        if (product.modifier_groups.length > 0) {
            setModifierTarget(product);

            return;
        }

        setDraft((current) => addToLines(current, product, []));
    }

    function sendDraft() {
        if (draft.length === 0 || busy) {
            return;
        }

        setBusy(true);
        router.post(
            `/pos/orders/${check.id}/items`,
            { items: draftPayload(draft) },
            {
                preserveScroll: true,
                onSuccess: () => setDraft([]),
                onFinish: () => setBusy(false),
            },
        );
    }

    function removeItem(item: CheckItem) {
        // Cooked items are voided with a reason; uncooked ones are just removed.
        if (item.completed) {
            setVoidTarget(item);
            setVoidReason('');

            return;
        }

        setBusy(true);
        router.delete(`/pos/order-items/${item.id}`, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    function confirmVoid() {
        if (!voidTarget || voidReason.trim() === '') {
            return;
        }

        setBusy(true);
        router.delete(`/pos/order-items/${voidTarget.id}`, {
            data: { reason: voidReason.trim() },
            preserveScroll: true,
            onSuccess: () => setVoidTarget(null),
            onFinish: () => setBusy(false),
        });
    }

    function closeTable(tenders: Tender[]) {
        setBusy(true);
        router.post(
            `/pos/v2/orders/${check.id}/close`,
            { tenders },
            { onFinish: () => setBusy(false) },
        );
    }

    const heading = (
        <Stack
            direction="row"
            sx={{ alignItems: 'center', gap: 1, minWidth: 0 }}
        >
            <IconButton
                onClick={() => router.get('/pos/v2/tables')}
                aria-label="К залам"
                edge="start"
            >
                <ArrowBackRoundedIcon />
            </IconButton>
            <Typography variant="h6" noWrap>
                {tableName}
            </Typography>
            <Chip
                size="small"
                label={check.number}
                variant="outlined"
                sx={{ display: { xs: 'none', sm: 'flex' } }}
            />
        </Stack>
    );

    const panelTitle = (
        <Stack direction="row" sx={{ alignItems: 'center', gap: 1 }}>
            <Typography variant="h6">Счёт стола</Typography>
            {liveItems.length > 0 && (
                <Chip size="small" label={liveItems.length} />
            )}
        </Stack>
    );

    const panelBody = (
        <>
            <Box sx={{ flex: 1, minHeight: 0, overflowY: 'auto', px: 2.5 }}>
                {liveItems.length === 0 && draft.length === 0 && (
                    <Stack
                        sx={{
                            alignItems: 'center',
                            color: 'text.secondary',
                            mt: 8,
                            gap: 1,
                        }}
                    >
                        <ReceiptLongOutlinedIcon
                            sx={{ fontSize: 48, opacity: 0.4 }}
                        />
                        <Typography variant="body2">
                            Счёт пуст — добавьте позиции
                        </Typography>
                    </Stack>
                )}

                {liveItems.map((item) => (
                    <CheckItemRow
                        key={item.id}
                        item={item}
                        busy={busy}
                        onRemove={() => removeItem(item)}
                    />
                ))}

                {draft.length > 0 && (
                    <Box
                        sx={(theme) => ({
                            my: 2,
                            p: 2,
                            pt: 1.5,
                            borderRadius: 4,
                            border: 2,
                            borderStyle: 'dashed',
                            borderColor: 'primary.main',
                            bgcolor: alpha(theme.palette.primary.main, 0.04),
                        })}
                    >
                        <Typography
                            variant="overline"
                            color="primary"
                            sx={{ fontWeight: 700 }}
                        >
                            Новый раунд · не отправлен
                        </Typography>
                        {draft.map((line) => (
                            <DraftLineItem
                                key={line.key}
                                line={line}
                                onChangeQuantity={(delta) =>
                                    setDraft((current) =>
                                        changeLineQuantity(
                                            current,
                                            line.key,
                                            delta,
                                        ),
                                    )
                                }
                            />
                        ))}
                        <Button
                            fullWidth
                            size="large"
                            variant="contained"
                            startIcon={<SendRoundedIcon />}
                            onClick={sendDraft}
                            disabled={busy}
                            sx={{ mt: 2 }}
                        >
                            На станции · +{formatPrice(draftTotal)}
                        </Button>
                    </Box>
                )}
            </Box>

            <Box
                sx={{
                    p: 2.5,
                    pt: 2,
                    borderTop: 1,
                    borderColor: 'divider',
                    pb: 'calc(20px + env(safe-area-inset-bottom))',
                }}
            >
                <Stack
                    direction="row"
                    sx={{
                        justifyContent: 'space-between',
                        alignItems: 'baseline',
                        mb: 1.5,
                    }}
                >
                    <Typography variant="subtitle1" color="text.secondary">
                        Итого по счёту
                    </Typography>
                    <Typography
                        variant="h5"
                        sx={{ fontVariantNumeric: 'tabular-nums' }}
                    >
                        {formatPrice(check.total)}
                    </Typography>
                </Stack>
                <Button
                    fullWidth
                    size="large"
                    variant="contained"
                    color="success"
                    onClick={() => {
                        setSheetOpen(false);
                        setPaying(true);
                    }}
                    disabled={
                        liveItems.length === 0 || busy || draft.length > 0
                    }
                    sx={{ minHeight: 60 }}
                >
                    {draft.length > 0
                        ? 'Сначала отправьте раунд'
                        : `Закрыть стол · ${formatPrice(check.total)}`}
                </Button>
            </Box>
        </>
    );

    return (
        <PosShell title={tableName} section="tables" heading={heading}>
            <ProductCatalog categories={categories} onPick={pickProduct} />

            <OrderPanel
                title={panelTitle}
                count={liveItems.length + draftCount}
                total={check.total + draftTotal}
                mobileOpen={sheetOpen}
                onMobileOpenChange={setSheetOpen}
                mobileBarLabel={
                    draft.length > 0
                        ? `Счёт · новых ${draftCount}`
                        : 'Счёт стола'
                }
                mobileBarColor={draft.length > 0 ? 'primary' : 'success'}
            >
                {panelBody}
            </OrderPanel>

            {modifierTarget && (
                <ModifierDialog
                    product={modifierTarget}
                    confirmLabel="В раунд"
                    onConfirm={(selected) => {
                        setDraft((current) =>
                            addToLines(current, modifierTarget, selected),
                        );
                        setModifierTarget(null);
                    }}
                    onClose={() => setModifierTarget(null)}
                />
            )}

            <Dialog
                open={voidTarget !== null}
                onClose={() => setVoidTarget(null)}
                fullWidth
                maxWidth="xs"
            >
                <DialogTitle>Списать «{voidTarget?.name}»</DialogTitle>
                <DialogContent>
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ mb: 2 }}
                    >
                        Позиция уже приготовлена — укажите причину списания.
                    </Typography>
                    <Stack
                        direction="row"
                        sx={{ flexWrap: 'wrap', gap: 1, mb: 2 }}
                    >
                        {VOID_REASONS.map((reason) => (
                            <Chip
                                key={reason}
                                clickable
                                label={reason}
                                color={
                                    voidReason === reason ? 'error' : 'default'
                                }
                                variant={
                                    voidReason === reason
                                        ? 'filled'
                                        : 'outlined'
                                }
                                onClick={() => setVoidReason(reason)}
                            />
                        ))}
                    </Stack>
                    <TextField
                        autoFocus
                        fullWidth
                        label="Причина"
                        value={voidReason}
                        onChange={(event) => setVoidReason(event.target.value)}
                    />
                </DialogContent>
                <DialogActions sx={{ p: 2, gap: 1 }}>
                    <Button
                        onClick={() => setVoidTarget(null)}
                        size="large"
                        color="inherit"
                        sx={{ flex: 1 }}
                    >
                        Отмена
                    </Button>
                    <Button
                        onClick={confirmVoid}
                        disabled={voidReason.trim() === '' || busy}
                        size="large"
                        variant="contained"
                        color="error"
                        sx={{ flex: 1 }}
                    >
                        Списать
                    </Button>
                </DialogActions>
            </Dialog>

            {paying && (
                <PaymentDialog
                    title={`Оплата · ${tableName}`}
                    total={check.total}
                    busy={busy}
                    onSettle={closeTable}
                    onCancel={() => setPaying(false)}
                />
            )}
        </PosShell>
    );
}
