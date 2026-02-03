// backend/js/auth.js
// Authentication utilities for Food Ordering System frontend

import { API_BASE } from './config.js';
import { showMessage } from './utils.js';

// LocalStorage keys
const USER_STORAGE_KEY = 'food_ordering_user';
const TOKEN_STORAGE_KEY = 'food_ordering_token'; // reserved for future JWT if implemented

/**
 * Send registration request to backend
 * @param {string} fullName
 * @param {string} email
 * @param {string} phone
 * @param {string} password
 * @returns {Promise<object>} Response data or throws error
 */
async function registerUser(fullName, email, phone, password) {
    try {
        const response = await fetch(`${API_BASE}/index.php?route=auth&action=register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                full_name: fullName.trim(),
                email: email.trim(),
                phone: phone.trim(),
                password
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            const errorMsg = data.message || 'Registration failed';
            showMessage(errorMsg, 'error');
            throw new Error(errorMsg);
        }

        showMessage('Registration successful! Please log in.', 'success');
        return data;

    } catch (error) {
        showMessage(error.message || 'Network error during registration', 'error');
        throw error;
    }
}

/**
 * Send login request and store user data on success
 * @param {string} email
 * @param {string} password
 * @returns {Promise<object>} User data or throws error
 */
async function loginUser(email, password) {
    try {
        const response = await fetch(`${API_BASE}/index.php?route=auth&action=login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                email: email.trim(),
                password
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            const errorMsg = data.message || 'Login failed';
            showMessage(errorMsg, 'error');
            throw new Error(errorMsg);
        }

        // Store user data (excluding sensitive fields)
        const userData = {
            id: data.data.id,
            full_name: data.data.full_name,
            email: data.data.email,
            phone: data.data.phone,
            role_name: data.data.role_name,
            loggedInAt: new Date().toISOString()
        };

        localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(userData));
        // localStorage.setItem(TOKEN_STORAGE_KEY, data.token); // if JWT is added later

        showMessage(`Welcome back, ${userData.full_name}!`, 'success');
        return userData;

    } catch (error) {
        showMessage(error.message || 'Network error during login', 'error');
        throw error;
    }
}

/**
 * Log out the current user
 * Clears session data and optionally redirects
 */
function logoutUser(redirectTo = '/login.html') {
    localStorage.removeItem(USER_STORAGE_KEY);
    // localStorage.removeItem(TOKEN_STORAGE_KEY); // if used

    showMessage('You have been logged out.', 'info');

    // Redirect to login page (or home)
    if (redirectTo) {
        window.location.href = redirectTo;
    }
}

/**
 * Check if user is currently logged in
 * @returns {boolean}
 */
function isLoggedIn() {
    const user = localStorage.getItem(USER_STORAGE_KEY);
    if (!user) return false;

    try {
        const parsed = JSON.parse(user);
        // Optional: add expiration check if token has expiry
        return !!parsed.id && !!parsed.email;
    } catch {
        return false;
    }
}

/**
 * Get current logged-in user data
 * @returns {object|null}
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;

    try {
        return JSON.parse(localStorage.getItem(USER_STORAGE_KEY));
    } catch {
        return null;
    }
}

// Export functions (use with <script type="module">)
export {
    registerUser,
    loginUser,
    logoutUser,
    isLoggedIn,
    getCurrentUser
};

// For non-module usage (optional fallback)
window.Auth = {
    registerUser,
    loginUser,
    logoutUser,
    isLoggedIn,
    getCurrentUser
};