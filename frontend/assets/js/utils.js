// backend/js/utils.js
// Reusable utility functions for Food Ordering System frontend

/**
 * Validates an email address format
 * @param {string} email - Email to validate
 * @returns {boolean}
 */
const validateEmail = (email) => {
    if (!email || typeof email !== 'string') return false;
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email.trim());
};

/**
 * Validates phone number (digits only, optional leading +)
 * @param {string} phone - Phone number to validate
 * @returns {boolean}
 */
const validatePhone = (phone) => {
    if (!phone || typeof phone !== 'string') return false;
    const cleaned = phone.trim();
    const re = /^\+?[0-9\s\-()]{7,15}$/;
    return re.test(cleaned);
};

/**
 * Checks if a value is not empty (null, undefined, empty string)
 * @param {*} value - Value to check
 * @returns {boolean}
 */
const validateNotEmpty = (value) => {
    if (value === null || value === undefined) return false;
    if (typeof value === 'string') return value.trim().length > 0;
    return true;
};

/**
 * Formats a number as currency with 2 decimal places
 * @param {number} amount - Amount to format
 * @param {string} [currency='$'] - Currency symbol
 * @returns {string}
 */
const formatPrice = (amount, currency = '$') => {
    if (typeof amount !== 'number' || isNaN(amount)) {
        return `${currency}0.00`;
    }
    return `${currency}${Number(amount).toFixed(2)}`;
};

/**
 * Displays a message in console with type prefix
 * (Can be replaced with toast/UI notification later)
 * @param {string} message - Message to display
 * @param {string} [type='info'] - Message type: 'success', 'error', 'info'
 */
const showMessage = (message, type = 'info') => {
    const prefix = {
        success: '✅ SUCCESS:',
        error:   '❌ ERROR:',
        info:    'ℹ️ INFO:'
    }[type] || 'ℹ️';

    console.log(`${prefix} ${message}`);
};

/**
 * Safely parses JSON string
 * @param {string} response - JSON string to parse
 * @returns {object|null} Parsed object or null if invalid
 */
const parseJSON = (response) => {
    if (typeof response !== 'string') return null;

    try {
        return JSON.parse(response);
    } catch (error) {
        console.error('JSON parse error:', error.message);
        return null;
    }
};

// Export all utilities (use with type="module" in HTML)
export {
    validateEmail,
    validatePhone,
    validateNotEmpty,
    formatPrice,
    showMessage,
    parseJSON
};

// For non-module usage (optional fallback):
// window.Utils = { validateEmail, validatePhone, ... };