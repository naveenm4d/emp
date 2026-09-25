/**
 * Base styles for the invitation's shadow root: hides media placeholders
 * that are still empty (previews of unfinished designs), plus neutral
 * defaults for the RSVP block. Injected into the invitation's
 * shadow root *before* the template's own <style>, so any template can
 * restyle these classes:
 *
 *   .emp-rsvp[data-state]      awaiting | accepted | declined | expired | preview
 *   .emp-rsvp__message
 *   .emp-rsvp__actions
 *   .emp-rsvp__button(--accept | --decline)
 *   .emp-rsvp__note
 */
export const RSVP_STYLES = `
img[src=""], video[src=""], audio[src=""] { visibility: hidden; }
.emp-rsvp { margin: 28px auto 0; text-align: center; font: inherit; }
.emp-rsvp__message { margin: 0 0 16px; font-size: 1.05em; }
.emp-rsvp__actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
.emp-rsvp__button {
    min-width: 150px; padding: 12px 22px; border: 1px solid currentColor; border-radius: 6px;
    background: transparent; color: inherit; font: inherit; cursor: pointer;
    transition: opacity .15s ease, transform .15s ease;
}
.emp-rsvp__button:hover:not(:disabled) { transform: translateY(-1px); }
.emp-rsvp__button:disabled { opacity: .55; cursor: not-allowed; }
.emp-rsvp__button:focus-visible { outline: 2px solid currentColor; outline-offset: 3px; }
.emp-rsvp__note { margin: 12px 0 0; font-size: .85em; opacity: .75; }
`;
