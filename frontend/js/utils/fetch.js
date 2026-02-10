/**
 * Food Ordering System - Fetch Utility
 * Reusable HTTP request helpers using native Fetch API
 *
 * Features:
 * - Automatic JSON handling
 * - JWT Authorization header support
 * - Consistent error handling
 * - Helper methods for common HTTP verbs
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

// ────────────────────────────────────────────────
// Configuration & Constants
// ────────────────────────────────────────────────
const TOKEN_KEY = 'auth_token'; // Key used in localStorage

/**
 * Get JWT token from localStorage
 * @returns {string|null} JWT token or null if not found
 */
function getAuthToken() {
    return localStorage.getItem(TOKEN_KEY);
}

/**
 * Generic request function using Fetch API
 * @param {string} url - Full API endpoint URL
 * @param {string} [method='GET'] - HTTP method
 * @param {Object|null} [data=null] - Request body (for POST/PUT/PATCH)
 * @param {Object} [customHeaders={}] - Additional headers
 * @returns {Promise<any>} Parsed JSON response
 * @throws {Error} On network failure or non-2xx response
 */
async function request(url, method = 'GET', data = null, customHeaders = {}) {
    const headers = {
        ...customHeaders
    };

    // Only set JSON content type if not sending FormData
    if (!(data instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    // Automatically add Authorization header if token exists
    const token = getAuthToken();
    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    const config = {
        method,
        headers,
        credentials: 'same-origin' // include cookies if needed
    };

    // Add body for methods that support it
    if (data && ['POST', 'PUT', 'PATCH'].includes(method.toUpperCase())) {
        config.body = (data instanceof FormData) ? data : JSON.stringify(data);
    }

    try {
        const response = await fetch(url, config);

        // Handle non-JSON responses (e.g. 204 No Content)
        if (response.status === 204) {
            return null;
        }

        const contentType = response.headers.get('content-type');
        let result;

        if (contentType && contentType.includes('application/json')) {
            result = await response.json();
        } else {
            result = await response.text();
        }

        if (!response.ok) {
            // Try to get error message from API response
            const errorMessage = result.message || result.error || `HTTP ${response.status}`;
            const error = new Error(errorMessage);
            error.status = response.status;
            error.response = result;
            throw error;
        }

        return result;

    } catch (error) {
        // Network errors or parsing failures
        if (error.name === 'TypeError') {
            throw new Error('Network error or server unreachable');
        }
        throw error;
    }
}

/**
 * GET request helper
 * @param {string} url
 * @param {Object} [headers={}]
 * @returns {Promise<any>}
 */
export async function get(url, headers = {}) {
    return request(url, 'GET', null, headers);
}

/**
 * POST request helper
 * @param {string} url
 * @param {Object} [data={}]
 * @param {Object} [headers={}]
 * @returns {Promise<any>}
 */
export async function post(url, data = {}, headers = {}) {
    return request(url, 'POST', data, headers);
}

/**
 * PUT request helper
 * @param {string} url
 * @param {Object} [data={}]
 * @param {Object} [headers={}]
 * @returns {Promise<any>}
 */
export async function put(url, data = {}, headers = {}) {
    return request(url, 'PUT', data, headers);
}

/**
 * PATCH request helper
 * @param {string} url
 * @param {Object} [data={}]
 * @param {Object} [headers={}]
 * @returns {Promise<any>}
 */
export async function patch(url, data = {}, headers = {}) {
    return request(url, 'PATCH', data, headers);
}

/**
 * DELETE request helper
 * @param {string} url
 * @param {Object} [headers={}]
 * @returns {Promise<any>}
 */
export async function del(url, headers = {}) {
    return request(url, 'DELETE', null, headers);
}

// ────────────────────────────────────────────────
// Exports
// ────────────────────────────────────────────────
export {
    request,
    get,
    post,
    put,
    patch,
    del
};

// For non-module usage (optional fallback)
window.API_FETCH = {
    request,
    get,
    post,
    put,
    patch,
    del
};