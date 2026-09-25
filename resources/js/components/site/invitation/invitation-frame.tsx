import type { ReactNode } from 'react';
import { useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { RSVP_STYLES } from './rsvp-styles';

type InvitationFrameProps = {
    /** Generated server-side: the template's <style> + filled-in markup. */
    html: string;
    /** Rendered into the template's {{ rsvp }} mount point. */
    rsvp: ReactNode;
    onReady?: (root: ShadowRoot) => void;
};

/**
 * Displays the generated invitation inside a shadow root, so the
 * template's CSS and this app's CSS cannot affect each other. The only
 * interactive part (RSVP) is portalled into the template's mount point.
 * The HTML is display-only: innerHTML never runs <script>, and the CSP
 * blocks inline handlers.
 */
export function InvitationFrame({ html, rsvp, onReady }: InvitationFrameProps) {
    const host = useRef<HTMLDivElement>(null);
    const [mount, setMount] = useState<Element | null>(null);

    useLayoutEffect(() => {
        const element = host.current;

        if (!element) {
            return;
        }

        const root =
            element.shadowRoot ?? element.attachShadow({ mode: 'open' });
        root.innerHTML = `<style>${RSVP_STYLES}</style>${html}`;

        // Templates without {{ rsvp }} get the block at the end.
        let target = root.querySelector('[data-emp-rsvp]');

        if (!target) {
            target = document.createElement('div');
            target.setAttribute('data-emp-rsvp', '');
            root.append(target);
        }

        setMount(target);
        onReady?.(root);
    }, [html, onReady]);

    return (
        <>
            <div ref={host} data-invitation />
            {mount && createPortal(rsvp, mount)}
        </>
    );
}
