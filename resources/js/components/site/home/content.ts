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
    /** Price per billing option. A plan with one price shows it for both. */
    price: Record<Billing, number>;
    unit: Record<Billing, string>;
    cta: string;
    featured?: boolean;
    features: string[];
}

export const plans: Plan[] = [
    {
        id: 'starter',
        name: 'Starter',
        tagline: 'For a small get-together.',
        price: { monthly: 0, yearly: 0 },
        unit: { monthly: 'free forever', yearly: 'free forever' },
        cta: 'Start free',
        features: [
            '1 active event',
            'Up to 50 guests',
            'Classic template',
            'One-tap RSVP',
            'WhatsApp delivery',
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
        features: [
            'Up to 500 guests',
            'Every standard template',
            'Photos, video & background music',
            'Guest approval & capacity limits',
            'Public registration link',
            'Delivery & read tracking',
        ],
    },
    {
        id: 'studio',
        name: 'Studio',
        tagline: 'For planners and venues.',
        price: { monthly: 14990, yearly: 149900 },
        unit: { monthly: 'per month', yearly: 'per year' },
        cta: 'Talk to us',
        features: [
            'Unlimited events',
            'Up to 2,000 guests per event',
            'Custom templates for your brand',
            'Priority WhatsApp sending',
            'Dedicated account manager',
        ],
    },
];

export const comparison: {
    label: string;
    values: [string | boolean, string | boolean, string | boolean];
}[] = [
    { label: 'Active events', values: ['1', '1 per purchase', 'Unlimited'] },
    { label: 'Guests per event', values: ['50', '500', '2,000'] },
    { label: 'Templates', values: ['Classic', 'All standard', 'All + custom'] },
    { label: 'WhatsApp delivery', values: [true, true, true] },
    { label: 'Delivery & read tracking', values: [false, true, true] },
    { label: 'Photos & video', values: ['Photos', true, true] },
    { label: 'Background music', values: [false, true, true] },
    { label: 'Guest approval & capacity', values: [false, true, true] },
    { label: 'Public registration link', values: [false, true, true] },
    { label: 'Account manager', values: [false, false, true] },
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
        a: 'Standard templates are included in Celebration and Studio. Some premium designer templates have their own one-time price, which is shown before you choose one.',
    },
];

/** Sri Lankan rupees, written the local way: "Rs. 14,990". */
export function formatLkr(amount: number): string {
    return `Rs. ${new Intl.NumberFormat('en-LK', {
        maximumFractionDigits: 0,
    }).format(amount)}`;
}
