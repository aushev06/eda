import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import SearchRoundedIcon from '@mui/icons-material/SearchRounded';
import TuneRoundedIcon from '@mui/icons-material/TuneRounded';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import InputAdornment from '@mui/material/InputAdornment';
import Stack from '@mui/material/Stack';
import Tab from '@mui/material/Tab';
import Tabs from '@mui/material/Tabs';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useMemo, useState } from 'react';
import type { PosCategory, PosProduct } from './types';
import { formatPrice, STATION_LABELS } from './utils';

type Props = {
    categories: PosCategory[];
    onPick: (product: PosProduct) => void;
};

const STATION_COLORS: Record<string, string> = {
    kitchen: '#F97316',
    bar: '#6366F1',
};

function ProductCard({
    product,
    onPick,
}: {
    product: PosProduct;
    onPick: (product: PosProduct) => void;
}) {
    const hasOptions = product.modifier_groups.length > 0;

    return (
        <Card
            sx={{
                position: 'relative',
                overflow: 'hidden',
                transition: 'transform 120ms',
                '&:active': { transform: 'scale(0.97)' },
            }}
        >
            <Box
                sx={{
                    position: 'absolute',
                    inset: '0 auto 0 0',
                    width: 4,
                    bgcolor: STATION_COLORS[product.station],
                }}
            />
            <CardActionArea
                onClick={() => onPick(product)}
                sx={{
                    height: 116,
                    p: 1.75,
                    pl: 2.25,
                    display: 'flex',
                    flexDirection: 'column',
                    alignItems: 'stretch',
                    justifyContent: 'space-between',
                }}
            >
                <Typography
                    variant="subtitle1"
                    sx={{
                        lineHeight: 1.2,
                        display: '-webkit-box',
                        WebkitLineClamp: 2,
                        WebkitBoxOrient: 'vertical',
                        overflow: 'hidden',
                    }}
                >
                    {product.name}
                </Typography>
                <Stack direction="row" sx={{ alignItems: 'center', gap: 0.75 }}>
                    <Typography
                        variant="subtitle1"
                        color="primary"
                        sx={{
                            fontWeight: 700,
                            fontVariantNumeric: 'tabular-nums',
                            mr: 'auto',
                        }}
                    >
                        {formatPrice(product.price)}
                    </Typography>
                    {hasOptions && (
                        <TuneRoundedIcon
                            fontSize="small"
                            color="action"
                            titleAccess="Есть опции"
                        />
                    )}
                    <Typography variant="caption" color="text.secondary">
                        {STATION_LABELS[product.station]}
                    </Typography>
                </Stack>
            </CardActionArea>
        </Card>
    );
}

/**
 * Category tabs + live search + product grid shared by the cashier screen
 * and the table check editor. Search looks across every category.
 */
export default function ProductCatalog({ categories, onPick }: Props) {
    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(
        categories[0]?.id ?? null,
    );
    const [query, setQuery] = useState('');

    const trimmed = query.trim().toLowerCase();

    const products = useMemo(() => {
        if (trimmed !== '') {
            return categories
                .flatMap((category) => category.products)
                .filter((product) =>
                    product.name.toLowerCase().includes(trimmed),
                );
        }

        return (
            (
                categories.find(
                    (category) => category.id === activeCategoryId,
                ) ?? categories[0]
            )?.products ?? []
        );
    }, [categories, activeCategoryId, trimmed]);

    return (
        <Box
            sx={{
                flex: 1,
                minWidth: 0,
                display: 'flex',
                flexDirection: 'column',
            }}
        >
            <Stack
                direction="row"
                sx={{ alignItems: 'center', gap: 1.5, px: 2, pt: 2 }}
            >
                <TextField
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Поиск блюда"
                    size="small"
                    sx={{
                        width: { xs: '100%', sm: 280 },
                        '& .MuiOutlinedInput-root': {
                            borderRadius: 3,
                            bgcolor: 'background.paper',
                        },
                    }}
                    slotProps={{
                        input: {
                            startAdornment: (
                                <InputAdornment position="start">
                                    <SearchRoundedIcon fontSize="small" />
                                </InputAdornment>
                            ),
                            endAdornment: query && (
                                <InputAdornment position="end">
                                    <IconButton
                                        size="small"
                                        onClick={() => setQuery('')}
                                        aria-label="Очистить поиск"
                                    >
                                        <CloseRoundedIcon fontSize="small" />
                                    </IconButton>
                                </InputAdornment>
                            ),
                        },
                    }}
                />
                {trimmed !== '' && (
                    <Chip label={`Найдено: ${products.length}`} size="small" />
                )}
            </Stack>

            {trimmed === '' && (
                <Tabs
                    value={activeCategoryId ?? false}
                    onChange={(_, value: number) => setActiveCategoryId(value)}
                    variant="scrollable"
                    scrollButtons="auto"
                    allowScrollButtonsMobile
                    sx={{
                        px: 1,
                        mt: 1,
                        minHeight: 52,
                        '& .MuiTabs-indicator': { display: 'none' },
                        '& .MuiTab-root': {
                            minHeight: 40,
                            my: 0.75,
                            mx: 0.5,
                            px: 2.25,
                            borderRadius: 999,
                            border: 1,
                            borderColor: 'divider',
                            bgcolor: 'background.paper',
                        },
                        '& .MuiTab-root.Mui-selected': {
                            bgcolor: 'primary.main',
                            color: 'primary.contrastText',
                            borderColor: 'primary.main',
                        },
                    }}
                >
                    {categories.map((category) => (
                        <Tab
                            key={category.id}
                            value={category.id}
                            label={category.name}
                        />
                    ))}
                </Tabs>
            )}

            <Box
                sx={{
                    flex: 1,
                    minHeight: 0,
                    overflowY: 'auto',
                    p: 2,
                    pt: 1,
                    pb: { xs: 12, lg: 2 },
                    display: 'grid',
                    gap: 1.5,
                    gridAutoRows: 'min-content',
                    gridTemplateColumns:
                        'repeat(auto-fill, minmax(min(100%, 168px), 1fr))',
                }}
            >
                {products.map((product) => (
                    <ProductCard
                        key={product.id}
                        product={product}
                        onPick={onPick}
                    />
                ))}
                {products.length === 0 && (
                    <Typography
                        color="text.secondary"
                        sx={{
                            gridColumn: '1 / -1',
                            textAlign: 'center',
                            mt: 8,
                        }}
                    >
                        {trimmed ? 'Ничего не найдено' : 'В категории нет блюд'}
                    </Typography>
                )}
            </Box>
        </Box>
    );
}
