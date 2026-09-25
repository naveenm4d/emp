import { Link } from '@inertiajs/react';

import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function Pagination({ meta }: { meta: Paginated<unknown>['meta'] }) {
    if (meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-xs text-muted-foreground">
            <span>
                Showing {meta.from}–{meta.to} of {meta.total}
            </span>
            <nav className="flex flex-wrap gap-1">
                {meta.links.map((link, i) => {
                    const label = link.label
                        .replace('&laquo;', '‹')
                        .replace('&raquo;', '›');

                    return link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll
                            className={cn(
                                'rounded-md border px-2.5 py-1',
                                link.active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border hover:bg-muted',
                            )}
                        >
                            {label}
                        </Link>
                    ) : (
                        <span
                            key={i}
                            className="rounded-md border border-border px-2.5 py-1 opacity-40"
                        >
                            {label}
                        </span>
                    );
                })}
            </nav>
        </div>
    );
}
