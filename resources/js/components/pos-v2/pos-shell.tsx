import { Head, router, usePage } from '@inertiajs/react';
import LocalBarRoundedIcon from '@mui/icons-material/LocalBarRounded';
import PointOfSaleRoundedIcon from '@mui/icons-material/PointOfSaleRounded';
import RestaurantRoundedIcon from '@mui/icons-material/RestaurantRounded';
import TableRestaurantRoundedIcon from '@mui/icons-material/TableRestaurantRounded';
import AppBar from '@mui/material/AppBar';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import CssBaseline from '@mui/material/CssBaseline';
import { ThemeProvider } from '@mui/material/styles';
import type { Theme } from '@mui/material/styles';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { posTheme } from './theme';
import type { SharedPosProps } from './types';

export type PosSection = 'cashier' | 'tables' | 'kitchen' | 'bar';

const SECTIONS: {
    value: PosSection;
    label: string;
    href: string;
    icon: ReactNode;
}[] = [
    {
        value: 'cashier',
        label: 'Касса',
        href: '/pos/v2',
        icon: <PointOfSaleRoundedIcon />,
    },
    {
        value: 'tables',
        label: 'Залы',
        href: '/pos/v2/tables',
        icon: <TableRestaurantRoundedIcon />,
    },
    {
        value: 'kitchen',
        label: 'Кухня',
        href: '/pos/v2/kitchen',
        icon: <RestaurantRoundedIcon />,
    },
    {
        value: 'bar',
        label: 'Бар',
        href: '/pos/v2/bar',
        icon: <LocalBarRoundedIcon />,
    },
];

type Props = {
    title: string;
    section: PosSection | null;
    heading?: ReactNode;
    actions?: ReactNode;
    theme?: Theme;
    children: ReactNode;
};

function Clock() {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const timer = setInterval(() => setNow(new Date()), 15_000);

        return () => clearInterval(timer);
    }, []);

    return (
        <Typography
            variant="subtitle1"
            sx={{
                fontVariantNumeric: 'tabular-nums',
                display: { xs: 'none', sm: 'block' },
            }}
        >
            {now.toLocaleTimeString('ru-RU', {
                hour: '2-digit',
                minute: '2-digit',
            })}
        </Typography>
    );
}

/**
 * Full-height frame for every v2 POS screen: themed, with the section
 * switcher in the app bar and the page body filling the rest.
 */
export default function PosShell({
    title,
    section,
    heading,
    actions,
    theme = posTheme,
    children,
}: Props) {
    const { auth } = usePage<SharedPosProps>().props;
    const userName = auth.user?.name ?? '';

    return (
        <ThemeProvider theme={theme}>
            <CssBaseline />
            <Head title={title} />
            <Box
                sx={{
                    height: '100dvh',
                    display: 'flex',
                    flexDirection: 'column',
                    bgcolor: 'background.default',
                    userSelect: 'none',
                }}
            >
                <AppBar
                    position="static"
                    color="inherit"
                    elevation={0}
                    sx={{
                        borderBottom: 1,
                        borderColor: 'divider',
                        bgcolor: 'background.paper',
                    }}
                >
                    <Toolbar
                        sx={{ gap: { xs: 1, sm: 2 }, minHeight: { xs: 60 } }}
                    >
                        {heading ?? (
                            <Typography
                                variant="h6"
                                noWrap
                                sx={{ minWidth: 0, flexShrink: 1 }}
                            >
                                {title}
                            </Typography>
                        )}

                        <Tabs
                            value={section ?? false}
                            onChange={(_, value: PosSection) =>
                                router.visit(
                                    SECTIONS.find((s) => s.value === value)!
                                        .href,
                                )
                            }
                            variant="scrollable"
                            scrollButtons={false}
                            sx={{
                                ml: 'auto',
                                minHeight: 60,
                                '& .MuiTabs-indicator': {
                                    height: 3,
                                    borderRadius: 3,
                                },
                            }}
                        >
                            {SECTIONS.map((item) => (
                                <Tab
                                    key={item.value}
                                    value={item.value}
                                    icon={item.icon as React.ReactElement}
                                    iconPosition="start"
                                    label={
                                        <Box
                                            sx={{
                                                display: {
                                                    xs: 'none',
                                                    md: 'inline',
                                                },
                                            }}
                                        >
                                            {item.label}
                                        </Box>
                                    }
                                    aria-label={item.label}
                                    sx={{
                                        minHeight: 60,
                                        minWidth: { xs: 52, md: 96 },
                                        px: { xs: 1, md: 2 },
                                    }}
                                />
                            ))}
                        </Tabs>

                        {actions}
                        <Clock />
                        {userName && (
                            <Avatar
                                title={userName}
                                sx={{
                                    width: 36,
                                    height: 36,
                                    bgcolor: 'primary.main',
                                    fontSize: 15,
                                    display: { xs: 'none', sm: 'flex' },
                                }}
                            >
                                {userName.slice(0, 1).toUpperCase()}
                            </Avatar>
                        )}
                    </Toolbar>
                </AppBar>

                <Box
                    component="main"
                    sx={{ flex: 1, minHeight: 0, display: 'flex' }}
                >
                    {children}
                </Box>
            </Box>
        </ThemeProvider>
    );
}
