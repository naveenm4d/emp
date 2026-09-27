import { createInertiaApp } from '@inertiajs/react';

import { initializeTheme } from '@/lib/appearance';

const appName = import.meta.env.VITE_APP_NAME || 'EMP';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    progress: {
        color: '#D97757',
    },
});

initializeTheme();
