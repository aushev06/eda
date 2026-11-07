import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import Badge from '@mui/material/Badge';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';
import { formatPrice } from './utils';

type Props = {
    title: ReactNode;
    count: number;
    total: number;
    mobileOpen: boolean;
    onMobileOpenChange: (open: boolean) => void;
    mobileBarLabel: string;
    mobileBarColor?: 'primary' | 'success';
    hideMobileBar?: boolean;
    children: ReactNode;
};

/**
 * Right-hand order column on wide screens; on phones and small tablets it
 * collapses into a sticky bottom bar that opens the same content as a sheet.
 */
export default function OrderPanel({
    title,
    count,
    total,
    mobileOpen,
    onMobileOpenChange,
    mobileBarLabel,
    mobileBarColor = 'primary',
    hideMobileBar,
    children,
}: Props) {
    return (
        <>
            <Paper
                square
                elevation={0}
                sx={{
                    display: { xs: 'none', lg: 'flex' },
                    flexDirection: 'column',
                    width: 400,
                    flexShrink: 0,
                    borderLeft: 1,
                    borderColor: 'divider',
                }}
            >
                <Box sx={{ px: 2.5, pt: 2, pb: 1 }}>{title}</Box>
                {children}
            </Paper>

            {!hideMobileBar && (
                <Box
                    sx={{
                        display: { lg: 'none' },
                        position: 'fixed',
                        insetInline: 0,
                        bottom: 0,
                        zIndex: 20,
                        p: 1.5,
                        pb: 'calc(12px + env(safe-area-inset-bottom))',
                    }}
                >
                    <Button
                        fullWidth
                        size="large"
                        variant="contained"
                        color={mobileBarColor}
                        onClick={() => onMobileOpenChange(true)}
                        sx={{
                            justifyContent: 'space-between',
                            px: 2.5,
                            borderRadius: 4,
                            boxShadow: 6,
                        }}
                    >
                        <Stack
                            direction="row"
                            sx={{ alignItems: 'center', gap: 2 }}
                        >
                            <Badge
                                badgeContent={count}
                                color="secondary"
                                sx={{
                                    '& .MuiBadge-badge': {
                                        position: 'static',
                                        transform: 'none',
                                    },
                                }}
                            />
                            {mobileBarLabel}
                        </Stack>
                        <Typography
                            variant="h6"
                            component="span"
                            sx={{ fontVariantNumeric: 'tabular-nums' }}
                        >
                            {formatPrice(total)}
                        </Typography>
                    </Button>
                </Box>
            )}

            <Drawer
                anchor="bottom"
                open={mobileOpen}
                onClose={() => onMobileOpenChange(false)}
                sx={{ display: { lg: 'none' } }}
                slotProps={{
                    paper: {
                        sx: {
                            maxHeight: '90dvh',
                            borderTopLeftRadius: 24,
                            borderTopRightRadius: 24,
                            display: 'flex',
                            flexDirection: 'column',
                        },
                    },
                }}
            >
                <Box
                    sx={{
                        width: 40,
                        height: 4,
                        borderRadius: 2,
                        bgcolor: 'divider',
                        mx: 'auto',
                        mt: 1.25,
                    }}
                />
                <Stack
                    direction="row"
                    sx={{ alignItems: 'center', px: 2.5, pt: 1, pb: 1 }}
                >
                    <Box sx={{ flex: 1 }}>{title}</Box>
                    <IconButton
                        onClick={() => onMobileOpenChange(false)}
                        aria-label="Закрыть"
                    >
                        <CloseRoundedIcon />
                    </IconButton>
                </Stack>
                {children}
            </Drawer>
        </>
    );
}
