import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { parseISO, isToday, isTomorrow, differenceInDays, format, isPast } from 'date-fns';

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

    const date = typeof dateInput === 'string' ? parseISO(dateInput) : new Date(dateInput);
    const today = new Date();
    const diffInDays = differenceInDays(date, today);

    // 1. Today (Red)
    if (isToday(date)) {
        return { text: 'Today', className: 'text-red-500 font-bold' };
    }

    // 2. Overdue (Optional - Dark Red)
    if (isPast(date) && !isToday(date)) {
        return { text: format(date, 'MMM d'), className: 'text-red-800' };
    }

    // 3. Tomorrow until 5th day (Green)
    if (diffInDays >= 1 && diffInDays <= 4) {
        const text = isTomorrow(date) ? 'Tomorrow' : format(date, 'EEEE');
        return { text, className: 'text-green-600 font-medium' };
    }

    // 4. The rest (Default Gray/Slate)
    return {
        text: format(date, 'MMM d'),
        className: 'text-slate-500'
    };
};