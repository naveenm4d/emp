import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { FlashMessages } from '@/components/shared/flash-messages';

type AuthLayoutProps = {
    title: string;
    description?: string;
    badge?: string;
    children: ReactNode;
};

export default function AuthLayout({
    title,
    description,
    badge,
    children,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-background px-4 py-12">
            <Head title={title} />
            <div className="w-full max-w-sm">
                <div className="mb-8 flex flex-col items-center text-center">
                    <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary text-base font-extrabold text-primary-foreground">
                        E
                    </div>
                    {badge && (
                        <span className="mb-2 rounded-full border border-border px-2 py-0.5 text-[10px] font-medium tracking-wide text-muted-foreground uppercase">
                            {badge}
                        </span>
                    )}
                    <h1 className="text-xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                <div className="rounded-2xl bg-card p-6 shadow-card">
                    {children}
                </div>
            </div>
            <FlashMessages />
        </div>
    );
}
