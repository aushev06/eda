import { alpha, createTheme } from '@mui/material/styles';
import type { ThemeOptions } from '@mui/material/styles';

const fontFamily =
    '"Instrument Sans", "Inter", system-ui, -apple-system, "Segoe UI", sans-serif';

const shared: ThemeOptions = {
    shape: { borderRadius: 14 },
    typography: {
        fontFamily,
        button: { textTransform: 'none', fontWeight: 600, letterSpacing: 0 },
        h5: { fontWeight: 700 },
        h6: { fontWeight: 700 },
        subtitle1: { fontWeight: 600 },
        subtitle2: { fontWeight: 600 },
    },
    components: {
        MuiButton: {
            defaultProps: { disableElevation: true },
            styleOverrides: {
                root: { borderRadius: 12 },
                sizeLarge: { minHeight: 56, fontSize: '1.05rem' },
            },
        },
        MuiCard: {
            defaultProps: { variant: 'outlined' },
            styleOverrides: { root: { borderRadius: 16 } },
        },
        MuiChip: {
            styleOverrides: { root: { fontWeight: 600 } },
        },
        MuiDialog: {
            styleOverrides: { paper: { borderRadius: 20 } },
        },
        MuiTab: {
            styleOverrides: {
                root: { textTransform: 'none', fontWeight: 600, minHeight: 48 },
            },
        },
    },
};

/** Cashier, floor and check screens. */
export const posTheme = createTheme({
    ...shared,
    palette: {
        mode: 'light',
        primary: { main: '#0F766E', contrastText: '#FFFFFF' },
        secondary: { main: '#F59E0B', contrastText: '#1C1917' },
        success: { main: '#16A34A' },
        error: { main: '#DC2626' },
        background: { default: '#F1F5F4', paper: '#FFFFFF' },
        text: { primary: '#0F172A', secondary: '#64748B' },
        divider: alpha('#0F172A', 0.08),
    },
});

/** Kitchen and bar displays: dark, high-contrast, readable from a distance. */
export const kdsTheme = createTheme({
    ...shared,
    palette: {
        mode: 'dark',
        primary: { main: '#2DD4BF', contrastText: '#042F2E' },
        secondary: { main: '#FBBF24' },
        success: { main: '#22C55E', contrastText: '#052E16' },
        warning: { main: '#F59E0B' },
        error: { main: '#EF4444' },
        background: { default: '#0B1120', paper: '#131C2E' },
        divider: alpha('#FFFFFF', 0.08),
    },
});
