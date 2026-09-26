import type { ReactNode } from 'react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { RSVP_STYLES } from './rsvp-styles';

type InvitationFrameProps = {
    /** Generated server-side: the template's <style> + filled-in markup. */
    html: string;
    /** Rendered into the template's {{ rsvp }} mount point. */
    rsvp: ReactNode;
    /** The template's js/ files, run in order once the markup is mounted. */
    scripts?: string[];
    onReady?: (root: ShadowRoot) => void;
};

/**
 * Displays the generated invitation inside a shadow root, so the
 * template's CSS and this app's CSS cannot affect each other. RSVP is
 * portalled into the template's mount point.
 *
 * Template JS (the package's js/ files, uploaded by EMP staff) is loaded
 * after the markup is mounted. The markup lives in the shadow root, so
 * scripts reach it through `window.empInvitation.root`, not `document`.
 */
export function InvitationFrame({
    html,
    rsvp,
    scripts = [],
    onReady,
}: InvitationFrameProps) {
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

    const scriptList = scripts.join('\n');

    useEffect(() => {
        const root = host.current?.shadowRoot;

        if (!root || !scriptList) {
            return;
        }

        window.empInvitation = { root };

        const elements = scriptList.split('\n').map((src) => {
            const script = document.createElement('script');
            script.src = src;
            script.async = false; // run in the package's order
            script.dataset.empTemplateScript = '';
            document.body.append(script);

            return script;
        });

        return () => {
            elements.forEach((script) => script.remove());

            if (window.empInvitation?.root === root) {
                delete window.empInvitation;
            }
        };
    }, [html, scriptList]);

    return (
        <>
            <div ref={host} data-invitation />
            {mount && createPortal(rsvp, mount)}
        </>
    );
}
