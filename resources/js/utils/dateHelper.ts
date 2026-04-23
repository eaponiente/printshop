import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { parseISO, isToday, isTomorrow, differenceInDays, format, isPast, differenceInCalendarDays } from 'date-fns';

// Extend dayjs with required plugins
dayjs.extend(utc);
dayjs.extend(timezone);

const MANILA_TIMEZONE = 'Asia/Manila';

/**
 * Converts any valid date input to Asia/Manila time.
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

    // 1. Parse the date as UTC
    // 2. Convert to Manila Timezone
    // 3. Format the output
    return dayjs.utc(date).tz(MANILA_TIMEZONE).format(format);
};

export const readableDate = (dateInput: string | Date) => {
    if (!dateInput) return { text: '', className: '' };

    const date = typeof dateInput === 'string' ? parseISO(dateInput) : dateInput;

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
        className: 'text-slate-500'
    };
};