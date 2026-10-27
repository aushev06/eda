import { router, usePage } from '@inertiajs/react';
import DeleteSweepOutlinedIcon from '@mui/icons-material/DeleteSweepOutlined';
import ReceiptLongOutlinedIcon from '@mui/icons-material/ReceiptLongOutlined';
import ShoppingBagOutlinedIcon from '@mui/icons-material/ShoppingBagOutlined';
import TableRestaurantOutlinedIcon from '@mui/icons-material/TableRestaurantOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import Tooltip from '@mui/material/Tooltip';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import DraftLineItem from '@/components/pos-v2/draft-line-item';
import FlashSnackbar from '@/components/pos-v2/flash-snackbar';
import ModifierDialog from '@/components/pos-v2/modifier-dialog';
import OrderPanel from '@/components/pos-v2/order-panel';
import PaymentDialog from '@/components/pos-v2/payment-dialog';
import PosShell from '@/components/pos-v2/pos-shell';
import ProductCatalog from '@/components/pos-v2/product-catalog';
import type {
    DraftLine,
    PosCategory,
    PosProduct,
    SharedPosProps,
    Tender,
} from '@/components/pos-v2/types';
import {
    addToLines,
    changeLineQuantity,
    draftPayload,
    formatPrice,
    lineKey,
    lineTotal,
    xsrfToken,
} from '@/components/pos-v2/utils';

type PosTable = { id: number; number: number; label: string | null };

type PageProps = SharedPosProps & {
    categories: PosCategory[];
    tables: PosTable[];
    establishment_name: string | null;
};

type ModifierTarget = {
    product: PosProduct;
    selected: number[];
    editKey?: string;
};

export default function PosV2Cashier() {
    const { categories, tables, establishment_name, flash } =
        usePage<PageProps>().props;

    const [cart, setCart] = useState<DraftLine[]>([]);
    const [dineIn, setDineIn] = useState(false);
    const [tableId, setTableId] = useState<number | null>(null);
    const [modifierTarget, setModifierTarget] = useState<ModifierTarget | null>(
        null,
    );
    const [paymentTotal, setPaymentTotal] = useState<number | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [quoting, setQuoting] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);

    const total = cart.reduce((sum, line) => sum + lineTotal(line), 0);
    const count = cart.reduce((sum, line) => sum + line.quantity, 0);
    const needsTable = dineIn && !tableId;

    function pickProduct(product: PosProduct) {
        if (product.modifier_groups.length > 0) {
            setModifierTarget({ product, selected: [] });

            return;
        }

        setCart((current) => addToLines(current, product, []));
    }

    function applyModifiers(selected: number[]) {
        if (!modifierTarget) {
            return;
        }

        const { product, editKey } = modifierTarget;

        setCart((current) => {
            if (!editKey) {
                return addToLines(current, product, selected);
            }

            const edited = current.find((line) => line.key === editKey);

            if (!edited || lineKey(product.id, selected) === editKey) {
                return current;
            }

            return addToLines(
                current.filter((line) => line.key !== editKey),
                product,
                selected,
                edited.quantity,
            );
        });
        setModifierTarget(null);
    }

    function resetOrder() {
        setCart([]);
        setDineIn(false);
        setTableId(null);
    }

    async function openPayment() {
        if (cart.length === 0 || submitting || quoting || needsTable) {
            return;
        }

        // Authoritative server total so the payment pad and settlement agree.
        setQuoting(true);

        try {
            const response = await fetch('/pos/quote', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ items: draftPayload(cart) }),
            });

            if (response.ok) {
                const data: { total: number } = await response.json();
                setSheetOpen(false);
                setPaymentTotal(data.total);
            }
        } finally {
            setQuoting(false);
        }
    }

    function submitOrder(tenders: Tender[]) {
        setSubmitting(true);
        router.post(
            '/pos/orders',
            {
                delivery_type: dineIn ? 'dine_in' : 'pickup',
                table_id: dineIn ? tableId : null,
                items: draftPayload(cart),
                tenders,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetOrder();
                    setPaymentTotal(null);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    const panelTitle = (
        <Stack direction="row" sx={{ alignItems: 'center', gap: 1 }}>
            <Typography variant="h6">Новый заказ</Typography>
            {count > 0 && <Chip size="small" color="primary" label={count} />}
            {cart.length > 0 && (
                <Tooltip title="Очистить">
                    <IconButton
                        onClick={resetOrder}
                        sx={{ ml: 'auto' }}
                        aria-label="Очистить заказ"
                    >
                        <DeleteSweepOutlinedIcon />
                    </IconButton>
                </Tooltip>
            )}
        </Stack>
    );

    const panelBody = (
        <>
            <Box sx={{ px: 2.5, pb: 1.5 }}>
                <ToggleButtonGroup
                    exclusive
                    fullWidth
                    color="primary"
                    value={dineIn ? 'dine_in' : 'pickup'}
                    onChange={(_, value: string | null) => {
                        if (value === null) {
                            return;
                        }

                        setDineIn(value === 'dine_in');

                        if (value === 'pickup') {
                            setTableId(null);
                        }
                    }}
                    sx={{
                        '& .MuiToggleButton-root': {
                            py: 1.25,
                            gap: 1,
                            fontWeight: 600,
                            textTransform: 'none',
                        },
                    }}
                >
                    <ToggleButton value="pickup">
                        <ShoppingBagOutlinedIcon fontSize="small" /> С собой
                    </ToggleButton>
                    <ToggleButton value="dine_in">
                        <TableRestaurantOutlinedIcon fontSize="small" /> В зале
                    </ToggleButton>
                </ToggleButtonGroup>

                {dineIn && (
                    <Stack
                        direction="row"
                        sx={{ flexWrap: 'wrap', gap: 1, mt: 1.5 }}
                    >
                        {tables.map((table) => (
                            <Chip
                                key={table.id}
                                clickable
                                label={table.label ?? `Стол ${table.number}`}
                                color={
                                    tableId === table.id ? 'primary' : 'default'
                                }
                                variant={
                                    tableId === table.id ? 'filled' : 'outlined'
                                }
                                onClick={() => setTableId(table.id)}
                                sx={{
                                    height: 40,
                                    minWidth: 56,
                                    borderRadius: 2.5,
                                }}
                            />
                        ))}
                        {tables.length === 0 && (
                            <Typography variant="body2" color="text.secondary">
                                Нет активных столов
                            </Typography>
                        )}
                    </Stack>
                )}
            </Box>

            <Box sx={{ flex: 1, minHeight: 0, overflowY: 'auto', px: 2.5 }}>
                {cart.length === 0 ? (
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
                            Выберите блюда слева
                        </Typography>
                    </Stack>
                ) : (
                    cart.map((line) => (
                        <DraftLineItem
                            key={line.key}
                            line={line}
                            onChangeQuantity={(delta) =>
                                setCart((current) =>
                                    changeLineQuantity(
                                        current,
                                        line.key,
                                        delta,
                                    ),
                                )
                            }
                            onEdit={() =>
                                setModifierTarget({
                                    product: line.product,
                                    selected: line.modifierIds,
                                    editKey: line.key,
                                })
                            }
                        />
                    ))
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
                        Итого
                    </Typography>
                    <Typography
                        variant="h5"
                        sx={{ fontVariantNumeric: 'tabular-nums' }}
                    >
                        {formatPrice(total)}
                    </Typography>
                </Stack>
                <Button
                    fullWidth
                    size="large"
                    variant="contained"
                    onClick={openPayment}
                    disabled={
                        cart.length === 0 || submitting || quoting || needsTable
                    }
                    sx={{ minHeight: 60 }}
                >
                    {needsTable
                        ? 'Выберите стол'
                        : `К оплате · ${formatPrice(total)}`}
                </Button>
            </Box>
        </>
    );

    return (
        <PosShell title={establishment_name ?? 'Касса'} section="cashier">
            <ProductCatalog categories={categories} onPick={pickProduct} />

            <OrderPanel
                title={panelTitle}
                count={count}
                total={total}
                mobileOpen={sheetOpen}
                onMobileOpenChange={setSheetOpen}
                mobileBarLabel="Заказ"
                hideMobileBar={cart.length === 0}
            >
                {panelBody}
            </OrderPanel>

            {modifierTarget && (
                <ModifierDialog
                    product={modifierTarget.product}
                    initialSelected={modifierTarget.selected}
                    confirmLabel={
                        modifierTarget.editKey ? 'Сохранить' : 'В заказ'
                    }
                    onConfirm={applyModifiers}
                    onClose={() => setModifierTarget(null)}
                />
            )}

            {paymentTotal !== null && (
                <PaymentDialog
                    total={paymentTotal}
                    busy={submitting}
                    onSettle={submitOrder}
                    onCancel={() => setPaymentTotal(null)}
                />
            )}

            {flash.placed_order_number && (
                <FlashSnackbar
                    key={flash.placed_order_number}
                    message={
                        `Заказ ${flash.placed_order_number} отправлен на станции` +
                        (flash.change_due
                            ? ` · сдача ${formatPrice(flash.change_due)}`
                            : '')
                    }
                />
            )}
        </PosShell>
    );
}
