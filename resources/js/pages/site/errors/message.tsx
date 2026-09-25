import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

/** Shared layout for the guest-facing error pages. */
export function ErrorMessage({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <main className="flex min-h-screen items-center justify-center px-6">
            <Head title={title} />
            <div className="max-w-md text-center">
                <p className="text-5xl" aria-hidden>
                    &#10086;
                </p>
                <h1 className="mt-4 text-2xl font-semibold">{title}</h1>
                <div className="mt-3 text-neutral-600">{children}</div>
            </div>
        </main>
    );
}
