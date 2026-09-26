/*
 * Mirrors the API Resources in app/Domains/{Domain}/Http/Resources.
 */

export type Option = { value: string; label: string };

export type ClientPlanKey = 'starter' | 'celebration' | 'business' | 'enterprise';

export type PlanFeature =
    | 'public_registration'
    | 'seating'
    | 'rsvp_list'
    | 'message_log';

/** The client's plan and what's used / left (ClientPlanService::usage). */
export type ClientPlanUsage = {
    plan: ClientPlanKey;
    label: string;
    active: boolean;
    expires_at: string | null;
    event_credits: number;
    events_used: number;
    /** null = unlimited */
    events_allowed: number | null;
    can_create_event: boolean;
    /** Why no event can be created now. */
    reason: string | null;
    max_guests_per_event: number | null;
    allows_extra_guests: boolean;
    features: PlanFeature[];
    message_limits: { invitations: number; reminders: number };
};

export type Client = {
    id: string;
    name: string;
    email: string;
    email_verified_at?: string | null;
    events_count?: number;
    plan?: ClientPlanKey;
    plan_label?: string;
    plan_active?: boolean;
    /** Staff only. */
    plan_expires_at?: string | null;
    event_credits?: number;
    created_at?: string;
};

/** One entry of the admin activity log. */
export type StaffActivity = {
    id: string;
    action: string;
    description: string;
    staff: { id: string; name: string } | null;
    client?: { id: string; name: string } | null;
    subject_type: string | null;
    subject_id: string | null;
    changes: Record<string, unknown> | null;
    note: string | null;
    ip: string | null;
    created_at: string;
};

export type StaffMember = {
    id: string;
    name: string;
    email: string;
    role: 'super_admin' | 'admin' | 'viewer';
    role_label: string;
    permissions: string[];
    is_active: boolean;
    last_login_at: string | null;
    created_at: string;
};

export type EventState = 'draft' | 'published' | 'cancelled';

export type RegistrationType = 'open' | 'approval_required' | 'guest_list_only';

/** A registration type as offered in the event form, with what it means. */
export type RegistrationTypeOption = {
    value: RegistrationType;
    label: string;
    description: string;
    details: { label: string; value: string; ok: boolean }[];
};

export type MessageLimits = {
    invitations: number;
    reminders: number;
    /** Staff only: the event's own values (null = platform default) and the defaults. */
    invitations_override?: number | null;
    reminders_override?: number | null;
    default_invitations?: number;
    default_reminders?: number;
};

export type Event = {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    state: EventState;
    allowed_transitions: EventState[];
    max_capacity: number;
    registration_type: RegistrationType;
    registration_type_label: string;
    registration_open: boolean;
    event_type: string | null;
    event_type_label: string | null;
    location_name: string | null;
    location_address: string | null;
    map_url: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    invitation_message: string | null;
    reminder_message: string | null;
    auto_reminders: boolean;
    remind_after_days: number;
    remind_before_days: number;
    /** Most guests the event can have (plan limit + extra guests); null = unlimited. */
    guest_limit: number | null;
    extra_guests: number;
    /** Most invitations / reminders each guest can get; staff also get the overrides and defaults. */
    message_limits: MessageLimits;
    /** The event's public URL (domain/{slug}/{code}); only on the event's own pages. */
    public_url?: string | null;
    public_link_open_count?: number;
    template_version_id: string;
    template?: EventTemplate;
    rendered_at: string | null;
    guests_count?: number;
    client?: Client;
    created_at: string;
    updated_at: string;
};

export type Guest = {
    id: string;
    event_id: string;
    name: string;
    email: string | null;
    phone: string | null;
    notes: string | null;
    invitation_message: string | null;
    reminder_message: string | null;
    source: 'manual' | 'public_link' | 'import' | 'api';
    approval_status: 'pending' | 'approved' | 'rejected' | 'waitlisted';
    approval_status_changed_at: string | null;
    /** The guest's personal link (domain/{slug}/{code}). */
    link_url?: string | null;
    link_open_count?: number;
    link_last_opened_at?: string | null;
    rsvp_status: 'not_sent' | 'pending' | 'confirmed' | 'declined' | 'maybe';
    check_in_status: 'not_checked_in' | 'checked_in';
    address: string | null;
    company: string | null;
    job_title: string | null;
    /** Plus-ones / children the client invited the guest with; null = the event's settings. */
    invited_additional_guests: number | null;
    invited_children: number | null;
    additional_guests: number;
    children: number;
    /** The guest plus their plus-ones and children. */
    party_size: number;
    dietary_restrictions: string[];
    dietary_notes: string | null;
    latest_rsvp?: Rsvp | null;
    /** Invitations / reminders sent so far, not counting failed ones. */
    messages_sent?: { invitations: number; reminders: number };
    /** Where the guest's party sits; null when not seated. */
    seating?: { table: string; seats: number[] } | null;
    created_at: string;
};

export type GuestRsvpStatus = Guest['rsvp_status'];

/** One seat at a table: empty, or the person sitting there. */
export type Seat = {
    number: number;
    guest_id: string | null;
    /** 0 = the guest, then their plus-ones, then their children. */
    party_member: number | null;
    /** "John", "John's guest", "John's child 2". */
    label: string | null;
    status: GuestRsvpStatus | null;
};

export type TableShape = 'round' | 'oval' | 'square' | 'rectangle';

export type EventTable = {
    id: string;
    name: string;
    seat_count: number;
    shape: TableShape;
    seats: Seat[];
};

/** A guest as the seating page sees them (SeatingGuestResource). */
export type SeatingGuest = {
    id: string;
    name: string;
    email: string | null;
    phone: string | null;
    rsvp_status: GuestRsvpStatus;
    additional_guests: number;
    children: number;
    party_size: number;
    dietary_restrictions: string[];
    dietary_notes: string | null;
    /** How many of the party have a seat. */
    seated: number;
    table_id: string | null;
    table_name: string | null;
    seat_numbers: number[];
    /** Every seated person of the party, wherever they sit (a party can be split). */
    seats: {
        party_member: number;
        label: string;
        table_id: string;
        table_name: string;
        seat_number: number;
    }[];
    /** Party members without a seat. */
    missing_members: { party_member: number; label: string }[];
};

export type SeatingSummary = {
    seats_total: number;
    seats_taken: number;
    confirmed_people: number;
    confirmed_seated: number;
    /** Confirmed guests whose party is (partly) without seats. */
    unseated: {
        guest_id: string;
        name: string;
        party_size: number;
        missing: number;
    }[];
};

export type GuestSummary = {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
    waitlisted: number;
    rsvp_confirmed: number;
    rsvp_declined: number;
    rsvp_maybe: number;
    /** Confirmed guests with their plus-ones and children. */
    headcount: number;
    /** Everyone holding a seat (pending or approved, not declined) with their party. */
    headcount_total: number;
};

export type RsvpStatus =
    | 'pending'
    | 'sent'
    | 'accepted'
    | 'declined'
    | 'maybe'
    | 'expired';

export type Rsvp = {
    id: string;
    event_id: string;
    guest_id: string;
    status: RsvpStatus;
    rsvp_url: string;
    is_expired: boolean;
    sent_at: string | null;
    expires_at: string | null;
    responded_at: string | null;
    /** The guest's note to the host when declining. */
    response_note: string | null;
    reminder_count: number;
    last_reminded_at: string | null;
    message?: RsvpMessage | null;
    guest?: Guest;
    created_at: string;
};

export type NotificationStatus =
    | 'pending'
    | 'sent'
    | 'delivered'
    | 'read'
    | 'failed';

/** Delivery status of the latest message sent for an RSVP link. */
export type RsvpMessage = {
    status: NotificationStatus;
    error: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    updated_at: string;
};

export type RsvpSummary = Record<RsvpStatus | 'total', number>;

export type MessageDelivery = {
    id: string;
    provider_id: string | null;
    status: string;
    raw_payload: Record<string, unknown> | null;
    created_at: string;
};

export type Notification = {
    id: string;
    event_id: string;
    guest_id: string | null;
    rsvp_id: string | null;
    channel: 'whatsapp' | 'email' | 'sms';
    /** What the message was sent for; null for older messages. */
    kind: 'rsvp_invitation' | 'rsvp_reminder' | null;
    status: NotificationStatus;
    recipient: string;
    message: string;
    error: string | null;
    attempts: number;
    sent_at: string | null;
    delivered_at: string | null;
    read_at: string | null;
    guest?: { id: string; name: string } | null;
    event?: {
        id: string;
        title: string;
        client: { id: string; name: string } | null;
    };
    deliveries?: MessageDelivery[];
    created_at: string;
    updated_at: string;
};

export type PlatformStats = {
    clients: number;
    events: number;
    eventsByState: Record<EventState, number>;
    guests: number;
    rsvps: number;
    notifications: number;
    failedNotifications: number;
};

export type MediaType = 'image' | 'video' | 'audio';

export type MediaSlot = {
    key: string;
    type: MediaType;
    required: boolean;
    label: string;
};

/** A catalogue entry in the template picker. */
export type Template = {
    id: string;
    key: string;
    name: string;
    description: string | null;
    author: string;
    category: string;
    category_label: string;
    tags: string[];
    price: number;
    currency: string;
    display_price: string | null;
    is_free: boolean;
    type: 'predefined' | 'custom';
    thumbnail_url: string | null;
    is_active: boolean;
    sort_order: number;
    versions_count?: number;
    client?: { id: string; name: string } | null;
    version: string | null;
    slots: MediaSlot[];
    media_summary: { images: number; videos: number; music: boolean };
};

/** One immutable version of a template's code (admin console). */
export type TemplateVersion = {
    id: string;
    version: string;
    published_at: string;
    is_latest: boolean;
    events_count?: number;
    has_scripts: boolean;
    slots: number;
};

/** The template an event is designed with (pinned version). */
export type EventTemplate = {
    id: string;
    key: string;
    name: string;
    version: string;
    latest_version: string | null;
    has_update: boolean;
};

export type UploadedMedia = {
    id: string;
    url: string;
    original_name: string | null;
    mime_type: string;
    size_bytes: number;
};

export type DesignSlot = MediaSlot & { media: UploadedMedia | null };

export type EventDesign = {
    slots: DesignSlot[];
    unused_media: {
        id: string;
        slot_key: string;
        type: MediaType;
        url: string;
        original_name: string | null;
    }[];
    missing: string[];
    preview_url: string;
    fonts: string[];
};
