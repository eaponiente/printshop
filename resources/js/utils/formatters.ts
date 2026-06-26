/**
 * Capitalizes the first letter of a string.
 * @param {string} str - The string to capitalize.
 * @returns {string} The capitalized string.
 */
export const capitalizeFirstLetter = (str: string): string => {
    if (!str) {
        return '';
    }

    return str.charAt(0).toUpperCase() + str.slice(1);
};

export const formatCurrency = (amount: number | null | undefined): string => {
    const safe = Number(amount) || 0;

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP',
    }).format(safe);
};

export const formatStatus = (status: string): string => {
    if (!status) {
        return '';
    }

    return status
        .split('_')
        .map(
            (word) =>
                word.charAt(0).toUpperCase() + word.slice(1).toLowerCase(),
        )
        .join(' ');
};

/**
 * Resolves the display label for a customer.
 * Prioritizes Full Name if First Name exists, otherwise falls back to Company.
 */
export const formatTime = (time: string | null): string => {
    if (!time) {
        return '';
    }

    const [h, m] = time.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour = h % 12 || 12;

    return `${hour}:${String(m).padStart(2, '0')} ${ampm}`;
};

/**
 * Convert a positive number into its English word form, suitable for the
 * "amount in words" line on a payslip. Handles up to 999,999,999.99.
 */
export const numberToWords = (amount: number): string => {
    const value = Math.max(0, Number(amount) || 0);
    const whole = Math.floor(value);
    const cents = Math.round((value - whole) * 100);

    const ones = [
        '',
        'one',
        'two',
        'three',
        'four',
        'five',
        'six',
        'seven',
        'eight',
        'nine',
        'ten',
        'eleven',
        'twelve',
        'thirteen',
        'fourteen',
        'fifteen',
        'sixteen',
        'seventeen',
        'eighteen',
        'nineteen',
    ];
    const tens = [
        '',
        '',
        'twenty',
        'thirty',
        'forty',
        'fifty',
        'sixty',
        'seventy',
        'eighty',
        'ninety',
    ];

    const underThousand = (n: number): string => {
        if (n === 0) {
            return '';
        }

        if (n < 20) {
            return ones[n];
        }

        if (n < 100) {
            const t = Math.floor(n / 10);
            const o = n % 10;

            return tens[t] + (o ? '-' + ones[o] : '');
        }

        const h = Math.floor(n / 100);
        const rest = n % 100;

        return ones[h] + ' hundred' + (rest ? ' ' + underThousand(rest) : '');
    };

    const wordsForInt = (n: number): string => {
        if (n === 0) {
            return 'zero';
        }

        const parts: string[] = [];
        const scales = [
            { value: 1_000_000_000, name: 'billion' },
            { value: 1_000_000, name: 'million' },
            { value: 1_000, name: 'thousand' },
        ];
        let remaining = n;

        for (const { value: v, name } of scales) {
            const count = Math.floor(remaining / v);

            if (count > 0) {
                parts.push(underThousand(count) + ' ' + name);
                remaining -= count * v;
            }
        }

        if (remaining > 0) {
            parts.push(underThousand(remaining));
        }

        return parts.join(' ');
    };

    const peso = wordsForInt(whole).toUpperCase();
    const tail =
        cents > 0
            ? ` AND ${cents.toString().padStart(2, '0')}/100`
            : ' AND 00/100';

    return `${peso} PESO${whole === 1 ? '' : 'S'}${tail}`;
};

export const getCustomerDisplayName = (customer?: {
    first_name?: string | null;
    full_name?: string | null;
    company?: string | null;
}) => {
    if (!customer) {
        return 'Unknown Customer';
    }

    // Check if first_name exists and isn't just whitespace
    const hasFirstName = !!customer.first_name?.trim();

    return hasFirstName
        ? (customer.full_name ?? 'Unknown Name')
        : (customer.company ?? 'Unknown Customer');
};
