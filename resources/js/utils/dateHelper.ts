import {
    parseISO,
    isToday,
    isTomorrow,
    format,
    isPast,
    differenceInCalendarDays,
} from 'date-fns';
import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

// Extend dayjs with required plugins
dayjs.extend(utc);
dayjs.extend(timezone);

const MANILA_LOCAL_PATTERN = /^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/;

/**
 * Converts any valid date input to Asia/Manila time.
 *
 * A string that carries NO timezone designator — a bare date
 * (e.g. `"2026-09-07"`, such as a `birth_date`) or an offset-less datetime
 * (e.g. `"2026-09-07 08:00:00"`, such as the backend's default
 * `serializeDate()` output) — is interpreted AS Manila wall time, because
 * the backend's app timezone (`config('app.timezone')`) is `Asia/Manila`:
 * that string already IS the Manila time, not an instant to convert, so it
 * must never shift for a viewer in another timezone. A string that DOES
 * carry a designator (a trailing `Z`, or an explicit `±HH:MM` offset, e.g.
 * `"2026-09-07T00:00:00.000000Z"` or `"2026-09-07T08:00:00+08:00"`) is an
 * unambiguous instant and is converted to Asia/Manila for display. A
 * non-string input (a `Date`, a timestamp) is likewise treated as an
 * instant and converted.
 *
 * @param date - Date string, number, or Date object
 * @param format - Desired output format (default: PHP-style datetime)
 */
export const toManilaTime = (
    date: string | number | Date | null | undefined,
    format: string = 'MMM DD, YYYY',
): string => {
    if (!date) {
        return 'N/A';
    }

    if (typeof date === 'string' && MANILA_LOCAL_PATTERN.test(date)) {
        return dayjs.tz(date, 'Asia/Manila').format(format);
    }

    return dayjs(date).tz('Asia/Manila').format(format);
};

/**
 * Converts any valid date input to an Asia/Manila time-of-day string
 * (e.g. "08:00 AM"). Use this instead of hand-rolling
 * `toLocaleTimeString`/`Date` formatting for time-of-day rendering.
 *
 * @param date - Date string, number, or Date object
 */
export const toManilaClock = (
    date: string | number | Date | null | undefined,
): string => toManilaTime(date, 'hh:mm A');

export const readableDate = (dateInput: string | Date) => {
    if (!dateInput) {
        return { text: '', className: '' };
    }

    const date =
        typeof dateInput === 'string' ? parseISO(dateInput) : dateInput;

    // Use Calendar Days to avoid the "24-hour" logic trap
    const today = new Date();
    const diffInCalendar = differenceInCalendarDays(date, today);

    // 1. Today (Red)
    if (isToday(date)) {
        return { text: 'Today', className: 'text-red-500 font-bold' };
    }

    // 2. Overdue (Past dates, not including today)
    if (isPast(date)) {
        return { text: format(date, 'MMM d'), className: 'text-red-800' };
    }

    // 3. Tomorrow until 4 days away (Green)
    // We use diffInCalendar === 1 for Tomorrow specifically
    if (diffInCalendar >= 1 && diffInCalendar <= 4) {
        const text = isTomorrow(date) ? 'Tomorrow' : format(date, 'EEEE');

        return { text, className: 'text-green-600 font-medium' };
    }

    // 4. Future dates (5+ days away)
    return {
        text: format(date, 'MMM d'),
        className: 'text-slate-500',
    };
};

export const toDateInput = (value: string | null | undefined): string => {
    if (!value) {
        return '';
    }

    return value.substring(0, 10);
};
