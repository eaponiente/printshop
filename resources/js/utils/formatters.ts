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

export const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP', // Change to your currency, e.g., 'PHP'
    }).format(amount);
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
