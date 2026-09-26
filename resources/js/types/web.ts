/*
 * Props of the guest-facing site pages. Shapes mirror the public resources
 * (app/Domains/{Domain}/Http/Resources/Public*Resource, RsvpResource).
 */

export type RsvpStatus =
    | 'pending'
    | 'sent'
    | 'accepted'
    | 'declined'
    | 'maybe'
    | 'expired';

export type Rsvp = {
    status: RsvpStatus;
    is_expired: boolean;
    expires_at: string | null;
    responded_at: string | null;
    /** The guest answered and the event still lets them change it. */
    can_change: boolean;
};

export type FieldRequirement = 'off' | 'optional' | 'required';

export type ContactField =
    | 'email'
    | 'phone'
    | 'address'
    | 'company'
    | 'job_title';

export type QuestionType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'select'
    | 'multi_select'
    | 'radio'
    | 'checkbox';

/** A custom question the client added on the event's Settings tab. */
export type RegistrationQuestion = {
    id: string;
    label: string;
    type: QuestionType;
    required: boolean;
    options: string[] | null;
};

export type AnswerValue = string | number | boolean | string[] | null;

/** What a guest is asked when registering or RSVPing (PublicRegistrationFormResource). */
export type RegistrationForm = {
    contact: Record<ContactField, FieldRequirement>;
    allow_maybe: boolean;
    plus_ones: boolean;
    max_additional_guests: number;
    children: boolean;
    max_children: number;
    dietary_options: { value: string; label: string }[];
    dietary_notes: boolean;
    questions: RegistrationQuestion[];
};

/** What the guest already answered, to fill the form in again. */
export type RegistrationResponse = {
    email: string | null;
    phone: string | null;
    address: string | null;
    company: string | null;
    job_title: string | null;
    additional_guests: number;
    children: number;
    dietary_restrictions: string[];
    dietary_notes: string | null;
    answers: Record<string, AnswerValue>;
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
    requires_approval: boolean;
};

export type PublicGuest = {
    id: string;
    name: string;
    approval_status: string;
    rsvp_status: string;
};

/** The invitation generated from the event's template. */
export type Design = {
    /** The template's <style> + filled-in markup. */
    html: string;
    /** Font stylesheets the template needs (loaded in <head>). */
    fonts: string[];
    /** The template's js/ files, run in order once the markup is mounted. */
    scripts: string[];
    has_bg_music: boolean;
    cover_image_url: string | null;
    template: { key: string; name: string; version: string };
    rendered_at: string | null;
};

export type InvitationPageProps = {
    mode: 'rsvp' | 'preview';
    event: PublicEvent;
    design: Design;
    rsvp: Rsvp | null;
    guest: PublicGuest | null;
    form: RegistrationForm;
    response: RegistrationResponse | null;
    actions: { respond: string } | null;
};

export type EventPageProps = {
    event: PublicEvent;
    design: Design;
    form: RegistrationForm;
    actions: { register: string };
};

export type SharedProps = {
    flash: { success: string | null; error: string | null };
};
