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

/** A calendar date (YYYY-MM-DD) read as that day, whatever the time zone. */
function calendarDate(value: string): Date {
    return new Date(`${value.slice(0, 10)}T00:00:00Z`);
}

function formatCalendar(
    value: string,
    options: Intl.DateTimeFormatOptions,
): string {
    return calendarDate(value).toLocaleDateString('en-GB', {
        timeZone: 'UTC',
        ...options,
    });
}

/** Month and day for a date tile: { month: 'NOV', day: '14' }. */
export function dateTileParts(value: string): { month: string; day: string } {
    return {
        month: formatCalendar(value, { month: 'short' }).toUpperCase(),
        day: formatCalendar(value, { day: '2-digit' }),
    };
}

/** "Sat 14 Nov" (with the year: "Sat 14 Nov 2026"). */
export function formatShortDate(
    value: string | null | undefined,
    withYear = false,
): string {
    if (!value) {
        return '—';
    }

    return formatCalendar(value, {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        ...(withYear ? { year: 'numeric' } : {}),
    }).replace(',', '');
}

/** "14 Nov" (with the year: "14 Nov 2026"). */
export function formatDayMonth(
    value: string | null | undefined,
    withYear = false,
): string {
    if (!value) {
        return '—';
    }

    return formatCalendar(value, {
        day: '2-digit',
        month: 'short',
        ...(withYear ? { year: 'numeric' } : {}),
    });
}

/** Whole days from today (Sri Lanka) to the date; negative when past. */
export function daysUntil(value: string): number {
    const today = calendarDate(todayInAppTimeZone()).getTime();

    return Math.round((calendarDate(value).getTime() - today) / 86_400_000);
}

/** "today", "tomorrow", "in 48 days", "3 days ago". */
export function inDays(value: string): string {
    const days = daysUntil(value);

    if (days === 0) {
        return 'today';
    }

    if (days === 1) {
        return 'tomorrow';
    }

    return days > 0 ? `in ${days} days` : `${-days} days ago`;
}

/** "6:30 PM" from a HH:MM time. */
export function formatTime(value: string | null | undefined): string {
    if (!value) {
        return '';
    }

    const [hours, minutes] = value.split(':').map(Number);

    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${hours < 12 ? 'AM' : 'PM'}`;
}

/** "Good morning" / "Good afternoon" / "Good evening" in Sri Lanka time. */
export function greeting(): string {
    const hour = Number(
        new Intl.DateTimeFormat('en-GB', {
            timeZone: APP_TIME_ZONE,
            hour: 'numeric',
            hourCycle: 'h23',
        }).format(new Date()),
    );

    if (hour < 12) {
        return 'Good morning';
    }

    return hour < 17 ? 'Good afternoon' : 'Good evening';
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
