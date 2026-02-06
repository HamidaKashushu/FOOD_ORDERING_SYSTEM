/**
 * Food Ordering System - API Configuration
 * Central configuration file for all backend API endpoints
 *
 * This file provides:
 * - Base URL (different for dev/prod)
 * - All API route paths organized by resource
 * - Consistent naming convention
 *
 * Import and use in other scripts like this:
 * import { API_BASE_URL, ENDPOINTS } from './config/api.js';
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

// ────────────────────────────────────────────────
// 1. API Base URL Configuration
// ────────────────────────────────────────────────
// Change this in production to your actual domain
const API_BASE_URL = 
    window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
        ? 'http://localhost/food-ordering-system/backend'
        : 'https://api.dfood.co.tz';  // ← Replace with your production domain

// Optional: API version prefix (if using versioning like /api/v1/)
const API_VERSION = '/api';

// ────────────────────────────────────────────────
// 2. API Endpoints - Organized by Resource
// ────────────────────────────────────────────────
const ENDPOINTS = {
    // Authentication
    AUTH: {
        LOGIN:     `${API_VERSION}/auth/login`,
        REGISTER:  `${API_VERSION}/auth/register`,
        REFRESH:   `${API_VERSION}/auth/refresh-token`,
        LOGOUT:    `${API_VERSION}/auth/logout`
    },

    // Users & Profile
    USERS: {
        PROFILE:        `${API_VERSION}/users/profile`,
        UPDATE_PROFILE: `${API_VERSION}/users/profile`,
        ALL_USERS:      `${API_VERSION}/users`,
        DELETE_USER:    (id) => `${API_VERSION}/users/${id}`,
        ASSIGN_ROLE:    (id) => `${API_VERSION}/users/${id}/role`
    },

    // Categories
    CATEGORIES: {
        GET_ALL:    `${API_VERSION}/categories`,
        GET_ONE:    (id) => `${API_VERSION}/categories/${id}`,
        CREATE:     `${API_VERSION}/categories`,
        UPDATE:     (id) => `${API_VERSION}/categories/${id}`,
        DELETE:     (id) => `${API_VERSION}/categories/${id}`,
        SEARCH:     (keyword) => `${API_VERSION}/categories/search/${encodeURIComponent(keyword)}`,
        STATUS:     (id) => `${API_VERSION}/categories/${id}/status`
    },

    // Products
    PRODUCTS: {
        GET_ALL:       `${API_VERSION}/products`,
        GET_ONE:       (id) => `${API_VERSION}/products/${id}`,
        GET_BY_CATEGORY: (catId) => `${API_VERSION}/products/category/${catId}`,
        SEARCH:        (keyword) => `${API_VERSION}/products/search/${encodeURIComponent(keyword)}`,
        CREATE:        `${API_VERSION}/products`,
        UPDATE:        (id) => `${API_VERSION}/products/${id}`,
        DELETE:        (id) => `${API_VERSION}/products/${id}`,
        STATUS:        (id) => `${API_VERSION}/products/${id}/status`
    },

    // Shopping Cart
    CART: {
        GET_CART:      `${API_VERSION}/cart`,
        ADD_ITEM:      `${API_VERSION}/cart/add`,
        UPDATE_ITEM:   `${API_VERSION}/cart/update`,
        REMOVE_ITEM:   `${API_VERSION}/cart/remove`,
        CLEAR_CART:    `${API_VERSION}/cart/clear`,
        GET_TOTAL:     `${API_VERSION}/cart/total`
    },

    // Orders
    ORDERS: {
        CREATE:           `${API_VERSION}/orders`,
        GET_ONE:          (id) => `${API_VERSION}/orders/${id}`,
        GET_USER_ORDERS:  `${API_VERSION}/orders/user`,
        GET_ALL:          `${API_VERSION}/orders`,
        UPDATE_STATUS:    (id) => `${API_VERSION}/orders/${id}/status`,
        DELETE:           (id) => `${API_VERSION}/orders/${id}`
    },

    // Payments
    PAYMENTS: {
        CREATE:           `${API_VERSION}/payments`,
        GET_ONE:          (id) => `${API_VERSION}/payments/${id}`,
        GET_USER_PAYMENTS: `${API_VERSION}/payments/user`,
        GET_BY_ORDER:     (orderId) => `${API_VERSION}/payments/order/${orderId}`,
        UPDATE_STATUS:    (id) => `${API_VERSION}/payments/${id}/status`,
        GET_ALL:          `${API_VERSION}/payments`
    },

    // Reports (Admin Only)
    REPORTS: {
        SALES:             `${API_VERSION}/reports/sales`,
        ORDERS:            `${API_VERSION}/reports/orders`,
        POPULAR_PRODUCTS:  `${API_VERSION}/reports/popular-products`,
        USER_ACTIVITY:     (userId) => `${API_VERSION}/reports/user-activity/${userId}`,
        REVENUE_SUMMARY:   `${API_VERSION}/reports/revenue-summary`
    }
};

// ────────────────────────────────────────────────
// 3. Exports
// ────────────────────────────────────────────────
export {
    API_BASE_URL,
    API_VERSION,
    ENDPOINTS
};

// For non-module usage (optional fallback)
window.API_CONFIG = {
    BASE_URL: API_BASE_URL,
    ENDPOINTS: ENDPOINTS
};