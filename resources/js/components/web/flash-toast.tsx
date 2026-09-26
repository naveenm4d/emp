import { usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types/web';

export function FlashToast() {
    const { flash } = usePage<SharedProps>().props;
    const message = flash.error ?? flash.success;

    if (!message) {
        return null;
    }

    return (
        <div
            role="status"
            className={cn(
                'fixed inset-x-4 top-4 z-50 mx-auto max-w-md rounded-lg px-4 py-3 text-center text-sm font-medium shadow-lg',
                flash.error
                    ? 'bg-red-600 text-white'
                    : 'bg-emerald-600 text-white',
            )}
        >
            {message}
        </div>
    );
}
