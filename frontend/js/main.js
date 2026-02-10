/**
 * Food Ordering System - Main Frontend Script (js/main.js)
 * Handles global UI behaviors, authentication state, cart count,
 * navigation toggles, and reusable utilities across all pages.
 *
 * This file should be included in every frontend HTML page.
 *
 * Features:
 * - Authentication state detection & UI updates
 * - Responsive mobile menu toggle
 * - Dynamic cart count in navigation
 * - Global notification/toast system
 * - Common formatting utilities (currency, date)
 * - Logout functionality with cleanup
 *
 * Dependencies:
 * - js/config/api.js
 * - js/utils/fetch.js
 * - js/utils/storage.js
 * - js/utils/auth.js
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { get } from './utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from './config/api.js';
import { getToken, getUser, isAuthenticated, logout } from './auth/auth.js';
import { getItem } from './utils/storage.js';

// ────────────────────────────────────────────────
// DOM Elements (global)
// ────────────────────────────────────────────────
const menuToggle = document.getElementById('menuToggle');
const navMenu = document.querySelector('.nav-menu');
const logoutBtn = document.getElementById('logoutBtn');
const loginBtn = document.getElementById('loginBtn');
const registerBtn = document.getElementById('registerBtn');
const cartCountBadge = document.getElementById('cart-count');
const profileLink = document.querySelector('a[href="profile.html"]');
const ordersLink = document.querySelector('a[href="orders.html"]');

// ────────────────────────────────────────────────
// Constants
// ────────────────────────────────────────────────
const TOAST_DURATION = 4000; // ms

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

/**
 * Format price to Tanzanian Shilling display
 * @param {number} amount
 * @returns {string}
 */
export function formatCurrency(amount) {
    return `TZS ${parseFloat(amount || 0).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    })}`;
}

/**
 * Format date to readable format
 * @param {string} dateStr - ISO date string
 * @returns {string}
 */
export function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Show global notification/toast message
 * @param {string} message - Message text
 * @param {string} type - 'success' | 'error' | 'warning' | 'info'
 */
export function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    document.body.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);

    // Auto-remove
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, TOAST_DURATION);
}

/**
 * Update navigation UI based on authentication state
 */
function updateNavigation() {
    const authenticated = isAuthenticated();
    const user = getUser();

    if (authenticated) {
        loginBtn.style.display = 'none';
        registerBtn.style.display = 'none';
        logoutBtn.style.display = 'inline-flex';

        // Show/hide admin-specific links
        if (user?.role === 'admin') {
            document.querySelectorAll('.admin-link').forEach(link => {
                link.style.display = 'inline';
            });
        }

        // Update profile link text
        if (profileLink) {
            profileLink.textContent = user.full_name || 'Profile';
        }

    } else {
        loginBtn.style.display = 'inline-flex';
        registerBtn.style.display = 'inline-flex';
        logoutBtn.style.display = 'none';
    }
}

/**
 * Update cart count in navigation bar
 */
async function updateCartCount() {
    if (!isAuthenticated()) {
        if (cartCountBadge) cartCountBadge.textContent = '0';
        return;
    }

    try {
        const cart = await get(`${API_BASE_URL}${ENDPOINTS.CART.GET_CART}`);
        const count = cart?.items?.length || 0;

        if (cartCountBadge) {
            cartCountBadge.textContent = count;
            cartCountBadge.style.display = count > 0 ? 'inline-flex' : 'none';
        }

    } catch (error) {
        console.error('Failed to update cart count:', error);
        if (cartCountBadge) cartCountBadge.textContent = '0';
    }
}

// ────────────────────────────────────────────────
// Event Listeners
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Responsive mobile menu toggle
    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
            menuToggle.querySelector('i').classList.toggle('fa-bars');
            menuToggle.querySelector('i').classList.toggle('fa-times');
        });
    }

    // Logout button
    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            logout();
        });
    }

    // Update navigation and cart count on load
    updateNavigation();
    updateCartCount();

    // Optional: Re-check cart count after page visibility change
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            updateCartCount();
        }
    });

    // Listen for custom "add-to-cart" events from other scripts
    document.addEventListener('add-to-cart', (e) => {
        if (e.detail && e.detail.productId) {
            import('./utils/cart.js').then(module => {
                module.addToCart(e.detail.productId, e.detail.quantity || 1);
            });
        }
    });
});

// ────────────────────────────────────────────────
// Global exports (for other scripts)
// ────────────────────────────────────────────────
export {
    formatCurrency,
    formatDate,
    showToast,
    updateCartCount,
    updateNavigation
};