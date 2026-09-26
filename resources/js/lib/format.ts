/** Times are shown in Sri Lanka time, whatever the viewer's device uses (matches config/app.php). */
export const APP_TIME_ZONE = 'Asia/Colombo';

/** Today's date in Sri Lanka as YYYY-MM-DD. */
export function todayInAppTimeZone(): string {
    return new Intl.DateTimeFormat('en-CA', {
        timeZone: APP_TIME_ZONE,
    }).format(new Date());
}

export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        timeZone: APP_TIME_ZONE,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        timeZone: APP_TIME_ZONE,
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const relativeTime = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
    style: 'long',
});

const timeUnits: [Intl.RelativeTimeFormatUnit, number][] = [
    ['year', 365 * 24 * 3600],
    ['month', 30 * 24 * 3600],
    ['week', 7 * 24 * 3600],
    ['day', 24 * 3600],
    ['hour', 3600],
    ['minute', 60],
];

/** "3 days ago", "23 hours ago", "in 4 days"; "just now" within a minute. */
export function timeAgo(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const seconds = (new Date(value).getTime() - Date.now()) / 1000;

    if (Math.abs(seconds) < 60) {
        return 'just now';
    }

    const [unit, size] =
        timeUnits.find(([, size]) => Math.abs(seconds) >= size) ??
        timeUnits[timeUnits.length - 1];

    return relativeTime.format(Math.round(seconds / size), unit);
}

export function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

/** A template's price label: its display price, else e.g. "Rs. 1,490" (LKR) from minor units. */
export function templatePrice(template: {
    display_price: string | null;
    currency: string;
    price: number;
}): string {
    if (template.display_price) {
        return template.display_price;
    }

    const amount = new Intl.NumberFormat('en-LK', {
        maximumFractionDigits: 2,
    }).format(template.price / 100);

    return template.currency === 'LKR'
        ? `Rs. ${amount}`
        : `${template.currency} ${amount}`;
}
