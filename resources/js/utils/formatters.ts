/**
 * Capitalizes the first letter of a string.
 * @param {string} str - The string to capitalize.
 * @returns {string} The capitalized string.
 */
export const capitalizeFirstLetter = (str: string): string => {
    if (!str) {
        return "";
    }

    return str.charAt(0).toUpperCase() + str.slice(1);
};

export const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP', // Change to your currency, e.g., 'PHP'
    }).format(amount);
};
