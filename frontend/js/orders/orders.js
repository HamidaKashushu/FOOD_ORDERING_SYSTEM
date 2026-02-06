/**
 * Food Ordering System - Orders Page Script
 * Manages fetching, displaying, and viewing details of user orders.
 *
 * Features:
 * - Loads user order history
 * - Displays order summary with status and total
 * - Shows detailed order items in modal
 * - Handles loading states and errors
 *
 * Dependencies:
 * - js/utils/fetch.js
 * - js/config/api.js
 * - js/utils/auth.js (for token)
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { get } from '../utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { getToken } from '../auth/auth.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const ordersListContainer = document.getElementById('ordersList');
const ordersLoading       = document.getElementById('ordersLoading');
const emptyOrdersMessage  = document.getElementById('emptyOrders');
const orderDetailsModal   = document.getElementById('orderDetailsModal');
const modalCloseBtn       = document.getElementById('closeModal');
const modalCloseBtnFooter = document.getElementById('closeModalBtn');
const orderInfoContainer  = document.getElementById('orderInfo');
const orderItemsList      = document.getElementById('orderItemsList');

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

/**
 * Format date to readable Tanzanian format
 * @param {string} dateStr - ISO date string
 * @returns {string}
 */
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format price to Tanzanian Shilling
 * @param {number} price
 * @returns {string}
 */
function formatPrice(price) {
    return `TZS ${parseFloat(price).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    })}`;
}

/**
 * Show/hide loading state
 * @param {boolean} show
 */
function toggleLoading(show) {
    ordersLoading.style.display = show ? 'block' : 'none';
    ordersListContainer.style.display = show ? 'none' : 'block';
}

/**
 * Show/hide empty state
 * @param {boolean} show
 */
function toggleEmptyState(show) {
    emptyOrdersMessage.style.display = show ? 'block' : 'none';
}

/**
 * Show message (success/error)
 * @param {string} message
 * @param {string} type - 'success' | 'error'
 */
function showMessage(message, type = 'error') {
    const msgEl = document.createElement('div');
    msgEl.className = `message ${type}`;
    msgEl.textContent = message;

    ordersListContainer.before(msgEl);

    setTimeout(() => {
        msgEl.remove();
    }, 5000);
}

// ────────────────────────────────────────────────
// Render Functions
// ────────────────────────────────────────────────

/**
 * Create order summary card
 * @param {Object} order
 * @returns {HTMLElement}
 */
function createOrderCard(order) {
    const card = document.createElement('div');
    card.className = 'order-card';
    card.dataset.orderId = order.id;

    card.innerHTML = `
        <div class="order-header">
            <div class="order-id">Order #${order.id}</div>
            <div class="order-date">${formatDate(order.created_at)}</div>
        </div>

        <div class="order-summary">
            <div class="order-total">
                <strong>Total:</strong> ${formatPrice(order.total_amount)}
            </div>
            <div class="order-status status-${order.status}">
                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
            </div>
        </div>

        <div class="order-actions">
            <button class="btn btn-outline view-details" data-order-id="${order.id}">
                View Details
            </button>
        </div>
    `;

    // View details handler
    card.querySelector('.view-details').addEventListener('click', () => {
        loadOrderDetails(order.id);
    });

    return card;
}

/**
 * Render list of orders
 * @param {Array} orders
 */
function renderOrders(orders) {
    ordersListContainer.innerHTML = '';

    if (orders.length === 0) {
        toggleEmptyState(true);
        return;
    }

    toggleEmptyState(false);

    orders.forEach(order => {
        const card = createOrderCard(order);
        ordersListContainer.appendChild(card);
    });
}

/**
 * Load and display order details in modal
 * @param {number} orderId
 */
async function loadOrderDetails(orderId) {
    try {
        const order = await get(
            `${API_BASE_URL}${ENDPOINTS.ORDERS.GET_ONE(orderId)}`
        );

        if (!order) {
            showMessage('Order not found', 'error');
            return;
        }

        // Populate summary
        orderInfoContainer.innerHTML = `
            <div class="order-detail-row">
                <strong>Order ID:</strong> #${order.id}
            </div>
            <div class="order-detail-row">
                <strong>Date:</strong> ${formatDate(order.created_at)}
            </div>
            <div class="order-detail-row">
                <strong>Status:</strong> 
                <span class="status-${order.status}">${order.status}</span>
            </div>
            <div class="order-detail-row">
                <strong>Total:</strong> ${formatPrice(order.total_amount)}
            </div>
            <div class="order-detail-row">
                <strong>Delivery Address:</strong> ${order.street || 'N/A'}, 
                ${order.city || ''}, ${order.region || ''} ${order.zip || ''}
            </div>
        `;

        // Populate items
        orderItemsList.innerHTML = '';

        if (order.items && order.items.length > 0) {
            order.items.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <img src="${item.image_url || '/assets/images/placeholder-product.jpg'}" 
                             alt="${item.name}" class="order-item-img">
                    </td>
                    <td>${item.name}</td>
                    <td>${formatPrice(item.price_at_time)}</td>
                    <td>${item.quantity}</td>
                    <td>${formatPrice(item.subtotal)}</td>
                `;
                orderItemsList.appendChild(row);
            });
        } else {
            orderItemsList.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center">No items found in this order</td>
                </tr>
            `;
        }

        // Show modal
        orderDetailsModal.style.display = 'block';

    } catch (error) {
        console.error('Failed to load order details:', error);
        showMessage('Failed to load order details', 'error');
    }
}

// ────────────────────────────────────────────────
// Main Load Function
// ────────────────────────────────────────────────

async function loadUserOrders() {
    const token = getToken();
    if (!token) {
        showMessage('Please login to view your orders', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
        return;
    }

    try {
        toggleLoading(true);
        const orders = await get(`${API_BASE_URL}${ENDPOINTS.ORDERS.GET_USER_ORDERS}`);

        renderOrders(orders || []);

    } catch (error) {
        console.error('Failed to load orders:', error);
        showMessage('Failed to load orders. Please try again.', 'error');
    } finally {
        toggleLoading(false);
    }
}

// ────────────────────────────────────────────────
// Event Listeners & Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load orders on page load
    loadUserOrders();

    // Close modal
    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', () => {
            orderDetailsModal.style.display = 'none';
        });
    }

    if (modalCloseBtnFooter) {
        modalCloseBtnFooter.addEventListener('click', () => {
            orderDetailsModal.style.display = 'none';
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        if (event.target === orderDetailsModal) {
            orderDetailsModal.style.display = 'none';
        }
    });
});