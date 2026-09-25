import * as React from 'react';

import { cn } from '@/lib/utils';

/** Native select styled to match the Input component. */
function Select({ className, ...props }: React.ComponentProps<'select'>) {
    return (
        <select
            data-slot="select"
            className={cn(
                'h-8 w-full min-w-0 rounded-lg border border-input bg-transparent px-2 text-sm transition-colors outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30',
                className,
            )}
            {...props}
        />
    );
}

export { Select };
