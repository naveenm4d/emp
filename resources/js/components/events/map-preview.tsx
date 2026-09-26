import { useHttp } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useEffect, useState } from 'react';

type MapPreviewProps = {
    /** The map link as typed in the form. */
    mapUrl: string;
    /** The endpoint that turns a map link into an embeddable map (client or admin area). */
    previewUrl: (mapUrl: string) => string;
};

/**
 * An embedded Google map of where the typed map link points, so the client can
 * check the link before guests use it. Short links are resolved by the server.
 */
export function MapPreview({ mapUrl, previewUrl }: MapPreviewProps) {
    const http = useHttp<Record<string, never>, { embed_url: string | null }>();
    const [checked, setChecked] = useState<string | null>(null);
    const url = mapUrl.trim();

    useEffect(() => {
        if (!/^https?:\/\//i.test(url)) {
            return;
        }

        // Wait until typing stops before asking the server.
        const timer = setTimeout(() => {
            http.cancel();
            http.get(previewUrl(url))
                .then(() => setChecked(url))
                .catch(() => setChecked(url));
        }, 600);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url]);

    if (!/^https?:\/\//i.test(url)) {
        return null;
    }

    if (http.processing || checked !== url) {
        return (
            <div className="aspect-video w-full animate-pulse rounded-lg bg-muted" />
        );
    }

    const embedUrl = http.response?.embed_url;

    if (!embedUrl) {
        return (
            <p className="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                <MapPin className="size-3.5" />
                Couldn't find a location in this link. Paste a Google Maps link
                (Share → Copy link).
            </p>
        );
    }

    return (
        <div className="space-y-1.5">
            <iframe
                title="Map preview"
                src={embedUrl}
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                className="aspect-video w-full rounded-lg border border-border"
            />
            <p className="text-xs text-muted-foreground">
                Check that the pin is at your venue. Guests open this link for
                directions.
            </p>
        </div>
    );
}
