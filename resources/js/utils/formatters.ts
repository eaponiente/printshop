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
