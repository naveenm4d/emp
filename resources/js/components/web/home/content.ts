/**
 * Every word on the homepage lives here, so copy and prices can change
 * without touching layout code. Prices are in Sri Lankan rupees (LKR).
 */

export type TemplateVariant = 'classic' | 'modern' | 'floral';

export const navLinks = [
    { href: '#features', label: 'Features' },
    { href: '#how-it-works', label: 'How it works' },
    { href: '#templates', label: 'Templates' },
    { href: '#pricing', label: 'Pricing' },
    { href: '#faq', label: 'FAQ' },
] as const;

export const occasions = [
    'Weddings',
    'Homecomings',
    'Engagements',
    'Birthdays',
    'Housewarmings',
    'Almsgivings',
    'Avurudu parties',
    'Batch reunions',
    'Baby showers',
    'Anniversaries',
];

export const steps = [
    {
        kicker: '01',
        title: 'Pick a template',
        body: 'Start from a designer template: Classic, Modern or Blush Florals. The layout, type and motion come ready.',
    },
    {
        kicker: '02',
        title: 'Make it yours',
        body: 'Add the date, venue and map link. Upload photos, a video and a song. Each guest’s name is filled in for them.',
    },
    {
        kicker: '03',
        title: 'Send on WhatsApp',
        body: 'Invitations go to your guests on WhatsApp with a rich link preview. You can see when each one is delivered and read.',
    },
    {
        kicker: '04',
        title: 'Watch the RSVPs arrive',
        body: 'Guests answer with one tap. No app and no sign-up. Your guest list updates by itself.',
    },
] as const;

export const templates: {
    variant: TemplateVariant;
    name: string;
    mood: string;
}[] = [
    {
        variant: 'classic',
        name: 'Classic',
        mood: 'Timeless serif, ivory and gold',
    },
    {
        variant: 'modern',
        name: 'Modern',
        mood: 'Bold type, midnight and brass',
    },
    {
        variant: 'floral',
        name: 'Blush Florals',
        mood: 'Soft petals, rose and sage',
    },
];

export const facts = [
    { value: 1, suffix: '', label: 'tap for a guest to RSVP' },
    { value: 0, suffix: '', label: 'apps for guests to install' },
    { value: 7, suffix: '-day', label: 'secure links by default' },
    { value: 3, suffix: '', label: 'designer templates to start' },
];

export type Billing = 'monthly' | 'yearly';

export interface Plan {
    id: string;
    name: string;
    tagline: string;
    /** Price per billing option (null = custom pricing). A plan with one price shows it for both. */
    price: Record<Billing, number> | null;
    unit: Record<Billing, string>;
    cta: string;
    featured?: boolean;
    /** Shown under the price, e.g. the extra-guests add-on. */
    note?: string;
    features: string[];
}

export const plans: Plan[] = [
    {
        id: 'starter',
        name: 'Starter',
        tagline: 'Try EMP with one small event.',
        price: { monthly: 0, yearly: 0 },
        unit: { monthly: 'free forever', yearly: 'free forever' },
        cta: 'Start free',
        features: [
            '1 event',
            'Up to 50 guests',
            'Guest list with personal RSVP links',
            'WhatsApp delivery: 1 invitation + 1 reminder per guest',
            'One-tap RSVP',
        ],
    },
    {
        id: 'celebration',
        name: 'Celebration',
        tagline: 'Everything your big day needs.',
        price: { monthly: 4990, yearly: 4990 },
        unit: { monthly: 'per event', yearly: 'per event' },
        cta: 'Plan my event',
        featured: true,
        note: '+ Rs. 1,000 per extra 100 guests',
        features: [
            'Up to 500 guests',
            'Every standard template',
            'Photos, video & background music',
            'Guest approval, capacity & public registration',
            'Seating plan',
            'RSVP tracking & delivery / read receipts',
            '2 logins: one for each of you',
        ],
    },
    {
        id: 'business',
        name: 'Business',
        tagline: 'For professional event organizers, companies and agencies.',
        price: { monthly: 14990, yearly: 149900 },
        unit: { monthly: 'per month', yearly: 'per year' },
        cta: 'Start with Business',
        note: '+ Rs. 1,000 per extra 100 guests',
        features: [
            'Unlimited events',
            'Up to 2,000 guests per event',
            'Custom templates for your brand',
            'Seating plans',
            'Priority WhatsApp sending',
            '5 team members with their own permissions',
        ],
    },
    {
        id: 'enterprise',
        name: 'Enterprise',
        tagline:
            'For large companies, universities, institutions and organizations.',
        price: null,
        unit: { monthly: 'custom pricing', yearly: 'custom pricing' },
        cta: 'Contact sales',
        features: [
            'Everything in Business',
            'Unlimited guests',
            'As many team members as you need',
            'Higher message limits',
            'Dedicated account manager',
            'Custom integrations & SLA',
            'Invoicing',
        ],
    },
];

export const comparison: {
    label: string;
    values: [
        string | boolean,
        string | boolean,
        string | boolean,
        string | boolean,
    ];
}[] = [
    {
        label: 'Events',
        values: ['1', '1 per purchase', 'Unlimited', 'Unlimited'],
    },
    { label: 'Guests per event', values: ['50', '500', '2,000', 'Unlimited'] },
    { label: 'People who sign in', values: ['1', '2', '5', 'Custom'] },
    {
        label: 'Extra guests',
        values: [false, 'Rs. 1,000 / 100', 'Rs. 1,000 / 100', 'Included'],
    },
    {
        label: 'Messages per guest',
        values: [
            '1 + 1 reminder',
            '3 + 3 reminders',
            '3 + 3 reminders',
            '5 + 5 reminders',
        ],
    },
    {
        label: 'Templates',
        values: ['Standard', 'All standard', 'All + custom', 'All + custom'],
    },
    {
        label: 'Public registration & approval',
        values: [false, true, true, true],
    },
    { label: 'Seating plans', values: [false, true, true, true] },
    { label: 'RSVP tracking', values: [false, true, true, true] },
    { label: 'Message log & read receipts', values: [false, true, true, true] },
    { label: 'Account manager', values: [false, false, false, true] },
    { label: 'Invoicing', values: [false, false, false, true] },
];

export const faqs = [
    {
        q: 'Do my guests need to download an app?',
        a: 'No. Guests open the link on WhatsApp and the invitation opens in their browser. Accepting or declining takes one tap.',
    },
    {
        q: 'Can I personalise each invitation?',
        a: 'Yes. Every invitation greets the guest by name, and you choose the photos, video and music that play with it.',
    },
    {
        q: 'How do I know who has seen the invitation?',
        a: 'EMP tracks each WhatsApp message as sent, delivered and read, and shows every guest’s RSVP on your dashboard as it happens.',
    },
    {
        q: 'What happens when an invitation link expires?',
        a: 'Links stay valid for 7 days by default. After that the guest sees a friendly expired page, and you can send a new invitation from the dashboard.',
    },
    {
        q: 'Can I control who attends?',
        a: 'Yes. Set a maximum capacity, turn on approval so you confirm each registration, or share a public link that guests can register through.',
    },
    {
        q: 'Are premium templates included?',
        a: 'Standard templates are included in every paid plan. Some premium designer templates have their own one-time price, which is shown before you choose one.',
    },
    {
        q: 'What does “one event” on Starter mean?',
        a: 'Starter is free for one event, ever: deleting it doesn’t free up another. For more events, pick Celebration (pay per event) or Business (unlimited events).',
    },
    {
        q: 'Can I invite more guests than my plan allows?',
        a: 'Yes, on Celebration and Business: add guests in blocks of 100 for Rs. 1,000 each, per event.',
    },
    {
        q: 'How does Enterprise pricing work?',
        a: 'Enterprise is priced for your organization: the number of events and guests, message volume, integrations and support. Contact us and we’ll put together a quote.',
    },
];

/** Sri Lankan rupees, written the local way: "Rs. 14,990". */
export function formatLkr(amount: number): string {
    return `Rs. ${new Intl.NumberFormat('en-LK', {
        maximumFractionDigits: 0,
    }).format(amount)}`;
}
