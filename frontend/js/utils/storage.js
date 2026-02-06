/**
 * Food Ordering System - Storage Utilities
 * Helper functions for localStorage and sessionStorage management.
 *
 * Features:
 * - Safe JSON serialization/deserialization
 * - Error handling with graceful fallbacks
 * - Convenient auth token helpers
 * - Consistent API for both storage types
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

const TOKEN_KEY = 'auth_token';

/**
 * Safely stringify value for storage
 * @param {*} value - Value to store
 * @returns {string} Stringified value
 */
function safeStringify(value) {
    try {
        return JSON.stringify(value);
    } catch (error) {
        console.error('Storage: Failed to stringify value', error);
        return String(value);
    }
}

/**
 * Safely parse stored value
 * @param {string} value - Stored string
 * @returns {*} Parsed value or original string if parsing fails
 */
function safeParse(value) {
    if (value === null || value === undefined) return null;
    try {
        return JSON.parse(value);
    } catch (error) {
        // Return original value if not valid JSON
        return value;
    }
}

/* ────────────────────────────────────────────────
   Local Storage Helpers
───────────────────────────────────────────────── */

/**
 * Store value in localStorage
 * @param {string} key - Storage key
 * @param {*} value - Value to store (object will be stringified)
 */
export function setItem(key, value) {
    try {
        localStorage.setItem(key, safeStringify(value));
    } catch (error) {
        console.error(`Storage: Failed to set item "${key}"`, error);
    }
}

/**
 * Retrieve value from localStorage
 * @param {string} key - Storage key
 * @returns {*} Parsed value or null if not found
 */
export function getItem(key) {
    try {
        const value = localStorage.getItem(key);
        return safeParse(value);
    } catch (error) {
        console.error(`Storage: Failed to get item "${key}"`, error);
        return null;
    }
}

/**
 * Remove item from localStorage
 * @param {string} key - Storage key
 */
export function removeItem(key) {
    try {
        localStorage.removeItem(key);
    } catch (error) {
        console.error(`Storage: Failed to remove item "${key}"`, error);
    }
}

/**
 * Clear all localStorage data
 */
export function clear() {
    try {
        localStorage.clear();
    } catch (error) {
        console.error('Storage: Failed to clear localStorage', error);
    }
}

/* ────────────────────────────────────────────────
   Session Storage Helpers
───────────────────────────────────────────────── */

/**
 * Store value in sessionStorage
 * @param {string} key - Storage key
 * @param {*} value - Value to store
 */
export function setSessionItem(key, value) {
    try {
        sessionStorage.setItem(key, safeStringify(value));
    } catch (error) {
        console.error(`SessionStorage: Failed to set item "${key}"`, error);
    }
}

/**
 * Retrieve value from sessionStorage
 * @param {string} key - Storage key
 * @returns {*} Parsed value or null
 */
export function getSessionItem(key) {
    try {
        const value = sessionStorage.getItem(key);
        return safeParse(value);
    } catch (error) {
        console.error(`SessionStorage: Failed to get item "${key}"`, error);
        return null;
    }
}

/**
 * Remove item from sessionStorage
 * @param {string} key - Storage key
 */
export function removeSessionItem(key) {
    try {
        sessionStorage.removeItem(key);
    } catch (error) {
        console.error(`SessionStorage: Failed to remove item "${key}"`, error);
    }
}

/**
 * Clear all sessionStorage data
 */
export function clearSession() {
    try {
        sessionStorage.clear();
    } catch (error) {
        console.error('SessionStorage: Failed to clear', error);
    }
}

/* ────────────────────────────────────────────────
   Authentication Token Helpers
───────────────────────────────────────────────── */

/**
 * Store JWT authentication token
 * @param {string} token - JWT token
 */
export function setToken(token) {
    setItem(TOKEN_KEY, token);
}

/**
 * Get stored JWT token
 * @returns {string|null} JWT token or null
 */
export function getToken() {
    return getItem(TOKEN_KEY);
}

/**
 * Remove stored JWT token (logout)
 */
export function removeToken() {
    removeItem(TOKEN_KEY);
}

/**
 * Check if user is authenticated (has valid token)
 * @returns {boolean}
 */
export function isAuthenticated() {
    return !!getToken();
}

// ────────────────────────────────────────────────
// Exports
// ────────────────────────────────────────────────
export {
    setItem,
    getItem,
    removeItem,
    clear,
    setSessionItem,
    getSessionItem,
    removeSessionItem,
    clearSession,
    setToken,
    getToken,
    removeToken,
    isAuthenticated
};