/*
 * Mirrors the API Resources in app/Domains/{Domain}/Http/Resources.
 */

export type Option = { value: string; label: string };

export type Client = {
    id: string;
    name: string;
    email: string;
    email_verified_at?: string | null;
    events_count?: number;
    created_at?: string;
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

export type Event = {
    id: string;
    title: string;
    slug: string;
    description: string | null;
    state: EventState;
    allowed_transitions: EventState[];
    max_capacity: number;
    require_approval: boolean;
    registration_open: boolean;
    event_type: string | null;
    location_name: string | null;
    location_address: string | null;
    map_url: string | null;
    event_date: string | null;
    start_time: string | null;
    end_time: string | null;
    public_url: string;
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
    source: 'manual' | 'public_link' | 'import' | 'api';
    approval_status: 'pending' | 'approved' | 'rejected' | 'waitlisted';
    rsvp_status: 'not_sent' | 'pending' | 'confirmed' | 'declined';
    check_in_status: 'not_checked_in' | 'checked_in';
    latest_invitation?: Invitation | null;
    created_at: string;
};

export type GuestSummary = {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
    waitlisted: number;
    rsvp_confirmed: number;
    rsvp_declined: number;
};

export type InvitationStatus =
    | 'pending'
    | 'sent'
    | 'accepted'
    | 'declined'
    | 'expired';

export type Invitation = {
    id: string;
    event_id: string;
    guest_id: string;
    status: InvitationStatus;
    rsvp_url: string;
    is_expired: boolean;
    sent_at: string | null;
    expires_at: string | null;
    responded_at: string | null;
    guest?: Guest;
    created_at: string;
};

export type InvitationSummary = Record<InvitationStatus | 'total', number>;

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
    channel: 'whatsapp' | 'email' | 'sms';
    status: 'pending' | 'sent' | 'delivered' | 'failed';
    recipient: string;
    message: string;
    error: string | null;
    attempts: number;
    sent_at: string | null;
    delivered_at: string | null;
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
    invitations: number;
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
    version: string | null;
    slots: MediaSlot[];
    media_summary: { images: number; videos: number; music: boolean };
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
