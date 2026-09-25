/*
 * Props of the guest-facing site pages. Shapes mirror the public resources
 * (app/Domains/{Domain}/Http/Resources/Public*Resource, RsvpResource).
 */

export type InvitationStatus =
    | 'pending'
    | 'sent'
    | 'accepted'
    | 'declined'
    | 'expired';

export type Invitation = {
    status: InvitationStatus;
    is_expired: boolean;
    expires_at: string | null;
    responded_at: string | null;
};

export type PublicEvent = {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    event_type: string | null;
    location_name: string | null;
    location_address: string | null;
    map_url: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    registration_open: boolean;
    require_approval: boolean;
};

export type PublicGuest = {
    id: string;
    name: string;
    approval_status: string;
    rsvp_status: string;
};

/** The invitation generated from the event's template. */
export type Design = {
    /** Sanitised HTML + the template's <style>; display only. */
    html: string;
    /** Font stylesheets the template needs (loaded in <head>). */
    fonts: string[];
    has_bg_music: boolean;
    cover_image_url: string | null;
    template: { key: string; name: string; version: string };
    rendered_at: string | null;
};

export type InvitationPageProps = {
    mode: 'rsvp' | 'preview';
    event: PublicEvent;
    design: Design;
    invitation: Invitation | null;
    guest: PublicGuest | null;
    actions: { accept: string; decline: string } | null;
};

export type EventPageProps = {
    event: PublicEvent;
    actions: { register: string };
};

export type SharedProps = {
    flash: { success: string | null; error: string | null };
};
