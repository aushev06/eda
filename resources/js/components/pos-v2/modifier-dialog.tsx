import CheckRoundedIcon from '@mui/icons-material/CheckRounded';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Stack from '@mui/material/Stack';
import { useTheme } from '@mui/material/styles';
import Typography from '@mui/material/Typography';
import useMediaQuery from '@mui/material/useMediaQuery';
import { useState } from 'react';
import type { PosModifierGroup, PosProduct } from './types';
import {
    formatPrice,
    groupSelectionCount,
    isSelectionValid,
    toggleModifierSelection,
} from './utils';

type Props = {
    product: PosProduct;
    initialSelected?: number[];
    confirmLabel: string;
    onConfirm: (selected: number[]) => void;
    onClose: () => void;
};

function groupRule(group: PosModifierGroup): string {
    if (group.min_select > 0 && group.min_select === group.max_select) {
        return group.max_select === 1
            ? 'Обязательно · выберите один'
            : `Обязательно · выберите ${group.max_select}`;
    }

    if (group.min_select > 0) {
        return `Обязательно · от ${group.min_select} до ${group.max_select}`;
    }

    return group.max_select === 1
        ? 'По желанию · один вариант'
        : `По желанию · до ${group.max_select}`;
}

export default function ModifierDialog({
    product,
    initialSelected = [],
    confirmLabel,
    onConfirm,
    onClose,
}: Props) {
    const theme = useTheme();
    const fullScreen = useMediaQuery(theme.breakpoints.down('sm'));
    const [selected, setSelected] = useState<number[]>(initialSelected);

    const valid = isSelectionValid(product, selected);
    const unitPrice =
        product.price +
        product.modifier_groups
            .flatMap((group) => group.modifiers)
            .filter((modifier) => selected.includes(modifier.id))
            .reduce((sum, modifier) => sum + modifier.price_delta, 0);

    return (
        <Dialog
            open
            onClose={onClose}
            fullScreen={fullScreen}
            fullWidth
            maxWidth="sm"
        >
            <DialogTitle sx={{ pb: 0.5 }}>
                {product.name}
                <Typography variant="body2" color="text.secondary">
                    {formatPrice(product.price)}
                </Typography>
            </DialogTitle>
            <DialogContent dividers sx={{ borderBottom: 0 }}>
                <Stack spacing={3}>
                    {product.modifier_groups.map((group) => {
                        const count = groupSelectionCount(group, selected);
                        const unmet = count < group.min_select;

                        return (
                            <Box key={group.id}>
                                <Stack
                                    direction="row"
                                    sx={{
                                        alignItems: 'baseline',
                                        justifyContent: 'space-between',
                                        mb: 1.25,
                                    }}
                                >
                                    <Box>
                                        <Typography variant="subtitle1">
                                            {group.name}
                                        </Typography>
                                        <Typography
                                            variant="caption"
                                            color={
                                                unmet
                                                    ? 'error'
                                                    : 'text.secondary'
                                            }
                                        >
                                            {groupRule(group)}
                                        </Typography>
                                    </Box>
                                    <Chip
                                        size="small"
                                        label={`${count} / ${group.max_select}`}
                                        color={unmet ? 'default' : 'primary'}
                                        variant={unmet ? 'outlined' : 'filled'}
                                    />
                                </Stack>
                                <Stack
                                    direction="row"
                                    sx={{ flexWrap: 'wrap', gap: 1 }}
                                >
                                    {group.modifiers.map((modifier) => {
                                        const active = selected.includes(
                                            modifier.id,
                                        );

                                        return (
                                            <Chip
                                                key={modifier.id}
                                                clickable
                                                onClick={() =>
                                                    setSelected((current) =>
                                                        toggleModifierSelection(
                                                            group,
                                                            modifier,
                                                            current,
                                                        ),
                                                    )
                                                }
                                                color={
                                                    active
                                                        ? 'primary'
                                                        : 'default'
                                                }
                                                variant={
                                                    active
                                                        ? 'filled'
                                                        : 'outlined'
                                                }
                                                icon={
                                                    active ? (
                                                        <CheckRoundedIcon />
                                                    ) : undefined
                                                }
                                                label={
                                                    modifier.price_delta > 0
                                                        ? `${modifier.name} · +${formatPrice(modifier.price_delta)}`
                                                        : modifier.name
                                                }
                                                sx={{
                                                    height: 44,
                                                    px: 0.5,
                                                    borderRadius: 3,
                                                    fontSize: '0.95rem',
                                                }}
                                            />
                                        );
                                    })}
                                </Stack>
                            </Box>
                        );
                    })}
                </Stack>
            </DialogContent>
            <DialogActions sx={{ p: 2, gap: 1 }}>
                <Button
                    onClick={onClose}
                    size="large"
                    color="inherit"
                    sx={{ flex: 1 }}
                >
                    Отмена
                </Button>
                <Button
                    onClick={() => onConfirm(selected)}
                    disabled={!valid}
                    variant="contained"
                    size="large"
                    sx={{ flex: 2 }}
                >
                    {confirmLabel} · {formatPrice(unitPrice)}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
