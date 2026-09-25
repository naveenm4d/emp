import { Head } from '@inertiajs/react';
import { useState } from 'react';

import { BackgroundMusic } from '@/components/site/invitation/background-music';
import { FlashToast } from '@/components/site/flash-toast';
import { InvitationFrame } from '@/components/site/invitation/invitation-frame';
import { RsvpActions } from '@/components/site/invitation/rsvp-actions';
import { useFonts } from '@/components/site/invitation/use-fonts';
import type { InvitationPageProps } from '@/types/site';

/**
 * Every template is shown by this one page: the server has already filled
 * the template's placeholders; we display it and add RSVP + music.
 */
export default function ShowInvitation({
    mode,
    event,
    design,
    invitation,
    actions,
}: InvitationPageProps) {
    const [root, setRoot] = useState<ShadowRoot | null>(null);
    useFonts(design.fonts);

    return (
        <>
            <Head
                title={
                    mode === 'preview'
                        ? `Preview · ${event.title}`
                        : event.title
                }
            />
            {mode === 'preview' && (
                <div className="sticky top-0 z-40 bg-neutral-900 px-4 py-2 text-center text-xs font-medium tracking-wide text-white">
                    Preview of {design.template.name} (v
                    {design.template.version}). Only you can see this page.
                </div>
            )}
            <FlashToast />
            <InvitationFrame
                html={design.html}
                onReady={setRoot}
                rsvp={
                    <RsvpActions
                        mode={mode}
                        invitation={invitation}
                        actions={actions}
                    />
                }
            />
            {design.has_bg_music && <BackgroundMusic root={root} />}
        </>
    );
}
