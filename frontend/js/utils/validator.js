/**
 * Food Ordering System - Client-Side Validation Utilities
 * Reusable functions for form input validation with clear error messages.
 *
 * All functions return an object with:
 *   valid: boolean
 *   message: string (empty if valid)
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

const DEFAULT_MESSAGES = {
    required: 'This field is required',
    email: 'Please enter a valid email address',
    password: 'Password must be at least {min} characters long',
    phone: 'Please enter a valid phone number',
    number: 'Please enter a valid number',
    minLength: 'Must be at least {min} characters',
    maxLength: 'Must not exceed {max} characters',
    passwordMatch: 'Passwords do not match'
};

/**
 * Check if value is present and not empty
 * @param {string} value - Input value
 * @param {string} [message] - Custom error message
 * @returns {{valid: boolean, message: string}}
 */
export function isRequired(value, message = DEFAULT_MESSAGES.required) {
    const trimmed = String(value ?? '').trim();
    return {
        valid: trimmed !== '' && trimmed !== null && trimmed !== undefined,
        message: trimmed ? '' : message
    };
}

/**
 * Validate email format
 * @param {string} value - Email address
 * @param {string} [message] - Custom error message
 * @returns {{valid: boolean, message: string}}
 */
export function isEmail(value, message = DEFAULT_MESSAGES.email) {
    if (!value) {
        return { valid: true, message: '' }; // let required handle empty
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return {
        valid: emailRegex.test(String(value).trim()),
        message: emailRegex.test(String(value).trim()) ? '' : message
    };
}

/**
 * Validate password strength (length + optional complexity)
 * @param {string} value - Password
 * @param {number} [minLength=8] - Minimum password length
 * @param {string} [message] - Custom error message
 * @returns {{valid: boolean, message: string}}
 */
export function isPassword(value, minLength = 8, message = DEFAULT_MESSAGES.password.replace('{min}', minLength)) {
    if (!value) {
        return { valid: true, message: '' };
    }

    const trimmed = String(value);
    return {
        valid: trimmed.length >= minLength,
        message: trimmed.length >= minLength ? '' : message
    };
}

/**
 * Validate phone number (simple digits check - can be extended)
 * @param {string} value - Phone number
 * @param {string} [message] - Custom error message
 * @returns {{valid: boolean, message: string}}
 */
export function isPhone(value, message = DEFAULT_MESSAGES.phone) {
    if (!value) {
        return { valid: true, message: '' };
    }

    const cleaned = String(value).replace(/\D/g, '');
    return {
        valid: cleaned.length >= 9 && cleaned.length <= 15,
        message: cleaned.length >= 9 && cleaned.length <= 15 ? '' : message
    };
}

/**
 * Check if value is numeric
 * @param {*} value - Input value
 * @param {string} [message] - Custom error message
 * @returns {{valid: boolean, message: string}}
 */
export function isNumber(value, message = DEFAULT_MESSAGES.number) {
    return {
        valid: !isNaN(parseFloat(value)) && isFinite(value),
        message: !isNaN(parseFloat(value)) && isFinite(value) ? '' : message
    };
}

/**
 * Check minimum string length
 * @param {string} value - Input value
 * @param {number} min - Minimum length
 * @param {string} [message] - Custom message
 * @returns {{valid: boolean, message: string}}
 */
export function minLength(value, min, message = DEFAULT_MESSAGES.minLength.replace('{min}', min)) {
    const len = String(value ?? '').trim().length;
    return {
        valid: len >= min,
        message: len >= min ? '' : message
    };
}

/**
 * Check maximum string length
 * @param {string} value - Input value
 * @param {number} max - Maximum length
 * @param {string} [message] - Custom message
 * @returns {{valid: boolean, message: string}}
 */
export function maxLength(value, max, message = DEFAULT_MESSAGES.maxLength.replace('{max}', max)) {
    const len = String(value ?? '').trim().length;
    return {
        valid: len <= max,
        message: len <= max ? '' : message
    };
}

/**
 * Check if two values match (e.g., password confirmation)
 * @param {string} value1 - First value
 * @param {string} value2 - Second value
 * @param {string} [message] - Custom error message
 * @returns {{valid: boolean, message: string}}
 */
export function matches(value1, value2, message = DEFAULT_MESSAGES.passwordMatch) {
    const val1 = String(value1 ?? '').trim();
    const val2 = String(value2 ?? '').trim();
    return {
        valid: val1 === val2,
        message: val1 === val2 ? '' : message
    };
}

/**
 * Validate entire form with field rules
 * @param {Object} values - Form values { fieldName: value }
 * @param {Object} rules - Validation rules { fieldName: (value) => ({valid, message}) }
 * @returns {{valid: boolean, errors: Object}}
 */
export function validateForm(values, rules) {
    const errors = {};
    let isValid = true;

    for (const field in rules) {
        const result = rules[field](values[field] ?? '');
        if (!result.valid) {
            isValid = false;
            errors[field] = result.message;
        }
    }

    return {
        valid: isValid,
        errors
    };
}

// ────────────────────────────────────────────────
// Exports
// ────────────────────────────────────────────────
export {
    isRequired,
    isEmail,
    isPassword,
    isPhone,
    isNumber,
    minLength,
    maxLength,
    matches,
    validateForm
};