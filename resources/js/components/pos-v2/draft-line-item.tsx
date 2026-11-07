import AddRoundedIcon from '@mui/icons-material/AddRounded';
import DeleteOutlineRoundedIcon from '@mui/icons-material/DeleteOutlineRounded';
import RemoveRoundedIcon from '@mui/icons-material/RemoveRounded';
import Box from '@mui/material/Box';
import ButtonBase from '@mui/material/ButtonBase';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { DraftLine } from './types';
import { formatPrice, lineModifiers, lineTotal, STATION_LABELS } from './utils';

type Props = {
    line: DraftLine;
    onChangeQuantity: (delta: number) => void;
    onEdit?: () => void;
};

/** One unsent line with a touch-sized quantity stepper. */
export default function DraftLineItem({
    line,
    onChangeQuantity,
    onEdit,
}: Props) {
    const modifiers = lineModifiers(line);
    const editable = !!onEdit && line.product.modifier_groups.length > 0;

    return (
        <Box sx={{ py: 1.5, borderBottom: 1, borderColor: 'divider' }}>
            <ButtonBase
                disabled={!editable}
                onClick={onEdit}
                sx={{
                    width: '100%',
                    justifyContent: 'space-between',
                    alignItems: 'flex-start',
                    textAlign: 'left',
                    gap: 1,
                    borderRadius: 1,
                }}
            >
                <Box sx={{ minWidth: 0 }}>
                    <Typography variant="subtitle1" sx={{ lineHeight: 1.25 }}>
                        {line.product.name}
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                        {STATION_LABELS[line.product.station]}
                        {modifiers.length > 0 &&
                            ' · ' +
                                modifiers
                                    .map((modifier) =>
                                        modifier.price_delta > 0
                                            ? `${modifier.name} +${formatPrice(modifier.price_delta)}`
                                            : modifier.name,
                                    )
                                    .join(', ')}
                    </Typography>
                </Box>
                <Typography
                    variant="subtitle1"
                    sx={{
                        whiteSpace: 'nowrap',
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {formatPrice(lineTotal(line))}
                </Typography>
            </ButtonBase>
            <Stack
                direction="row"
                sx={{
                    mt: 1,
                    alignItems: 'center',
                    width: 'fit-content',
                    border: 1,
                    borderColor: 'divider',
                    borderRadius: 999,
                    bgcolor: 'background.default',
                }}
            >
                <IconButton
                    onClick={() => onChangeQuantity(-1)}
                    aria-label="Меньше"
                    sx={{ width: 44, height: 44 }}
                >
                    {line.quantity === 1 ? (
                        <DeleteOutlineRoundedIcon />
                    ) : (
                        <RemoveRoundedIcon />
                    )}
                </IconButton>
                <Typography
                    variant="subtitle1"
                    sx={{
                        minWidth: 32,
                        textAlign: 'center',
                        fontVariantNumeric: 'tabular-nums',
                    }}
                >
                    {line.quantity}
                </Typography>
                <IconButton
                    onClick={() => onChangeQuantity(1)}
                    aria-label="Больше"
                    sx={{ width: 44, height: 44 }}
                >
                    <AddRoundedIcon />
                </IconButton>
            </Stack>
        </Box>
    );
}
