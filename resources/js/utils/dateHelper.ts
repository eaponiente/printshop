import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

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
