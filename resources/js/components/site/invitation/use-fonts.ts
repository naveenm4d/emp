import { useEffect } from 'react';

/**
 * @font-face rules do not work inside a shadow root, so the template's
 * font stylesheets are added to the document <head> instead.
 */
export function useFonts(urls: string[]) {
    const key = urls.join('\n');

    useEffect(() => {
        const links = key
            .split('\n')
            .filter(Boolean)
            .map((href) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = href;
                document.head.append(link);

                return link;
            });

        return () => links.forEach((link) => link.remove());
    }, [key]);
}
