/**
 * Food Ordering System - Authentication Utility
 * Manages user session, token storage, and auth state.
 *
 * @package FoodOrderingSystem
 */

import { post } from './fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { setItem, getItem, removeItem, clear } from './storage.js';

const TOKEN_KEY = 'auth_token';
const USER_KEY = 'auth_user';

/**
 * Login user and save session
 * @param {string} email
 * @param {string} password
 * @returns {Promise<Object>} User object
 */
export async function login(email, password) {
    try {
        const response = await post(`${API_BASE_URL}${ENDPOINTS.AUTH.LOGIN}`, { email, password });

        if (response.token && response.user) {
            setSession(response.token, response.user);
            return response.user;
        }

        throw new Error('Invalid response from server');
    } catch (error) {
        throw error;
    }
}

/**
 * Register new user
 * @param {Object} userData
 * @returns {Promise<Object>}
 */
export async function register(userData) {
    return await post(`${API_BASE_URL}${ENDPOINTS.AUTH.REGISTER}`, userData);
}

/**
 * Logout user and clear session
 */
export function logout() {
    removeItem(TOKEN_KEY);
    removeItem(USER_KEY);
    // Redirect to login page
    window.location.href = '/food-ordering-system/frontend/login.html';
}

/**
 * Save session data
 * @param {string} token
 * @param {Object} user
 */
export function setSession(token, user) {
    setItem(TOKEN_KEY, token);
    setItem(USER_KEY, user);
}

/**
 * Get current auth token
 * @returns {string|null}
 */
export function getToken() {
    return getItem(TOKEN_KEY);
}

/**
 * Get current user data
 * @returns {Object|null}
 */
export function getUser() {
    return getItem(USER_KEY);
}

/**
 * Check if user is authenticated
 * @returns {boolean}
 */
export function isAuthenticated() {
    return !!getToken();
}

/**
 * Check if user has admin role
 * @returns {boolean}
 */
export function isAdmin() {
    const user = getUser();
    return user && user.role_name === 'admin';
}

/**
 * Require auth for a page (redirect if not logged in)
 */
export function requireAuth() {
    if (!isAuthenticated()) {
        window.location.href = '/food-ordering-system/frontend/login.html';
    }
}

/**
 * Require admin role (redirect if not admin)
 */
export function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        window.location.href = '/food-ordering-system/frontend/index.html';
    }
}
