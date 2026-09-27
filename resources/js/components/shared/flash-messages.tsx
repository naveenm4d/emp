import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import { cn } from '@/lib/utils';

/**
 * Shows the session flash (`success` / `error`) set by InertiaController
 * helpers and by DomainException rendering in bootstrap/app.php.
 */
export function FlashMessages() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState<{
        type: 'success' | 'error';
        message: string;
    } | null>(null);

    useEffect(() => {
        if (flash.error) {
            setVisible({ type: 'error', message: flash.error });
        } else if (flash.success) {
            setVisible({ type: 'success', message: flash.success });
        }
    }, [flash]);

    useEffect(() => {
        if (!visible) {
            return;
        }

        const timer = setTimeout(() => setVisible(null), 5000);

        return () => clearTimeout(timer);
    }, [visible]);

    if (!visible) {
        return null;
    }

    const Icon = visible.type === 'success' ? CheckCircle2 : XCircle;

    return (
        <div className="fixed right-4 bottom-4 z-[60] max-w-sm" role="status">
            <div
                className={cn(
                    'flex items-start gap-2.5 rounded-lg border bg-card p-3 pr-2 text-sm shadow-lg',
                    visible.type === 'success'
                        ? 'border-success/30'
                        : 'border-destructive/30',
                )}
            >
                <Icon
                    className={cn(
                        'mt-0.5 h-4 w-4 shrink-0',
                        visible.type === 'success'
                            ? 'text-success'
                            : 'text-destructive',
                    )}
                />
                <p className="flex-1 text-foreground">{visible.message}</p>
                <button
                    type="button"
                    onClick={() => setVisible(null)}
                    className="text-muted-foreground hover:text-foreground"
                    aria-label="Dismiss"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
