import Alert from '@mui/material/Alert';
import Snackbar from '@mui/material/Snackbar';
import { useState } from 'react';

/** Self-dismissing confirmation; remount it with a new key for every message. */
export default function FlashSnackbar({ message }: { message: string }) {
    const [open, setOpen] = useState(true);

    return (
        <Snackbar
            open={open}
            autoHideDuration={5000}
            onClose={() => setOpen(false)}
            anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
            sx={{ bottom: { xs: 96, lg: 24 } }}
        >
            <Alert
                onClose={() => setOpen(false)}
                severity="success"
                variant="filled"
                sx={{ fontSize: '1rem', alignItems: 'center' }}
            >
                {message}
            </Alert>
        </Snackbar>
    );
}
