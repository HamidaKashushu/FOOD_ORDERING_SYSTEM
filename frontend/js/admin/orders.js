/**
 * Food Ordering System - Admin Orders Management Script
 * Handles viewing all orders, updating status, and viewing order details
 * in the admin panel.
 *
 * Features:
 * - Fetch and display all orders with filtering
 * - View detailed order items in modal
 * - Update order status with immediate UI feedback
 * - Dynamic success/error messages
 * - Responsive table and modal design
 *
 * Dependencies:
 * - js/utils/fetch.js
 * - js/config/api.js
 * - js/utils/auth.js (for token)
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { get, patch } from '../utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { getToken } from '../auth/auth.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const ordersTableBody     = document.getElementById('ordersBody');
const ordersLoading       = document.getElementById('ordersLoading');
const emptyOrders         = document.getElementById('emptyOrders');
const statusFilter        = document.getElementById('statusFilter');
const searchInput         = document.getElementById('searchInput');

const orderDetailsModal   = document.getElementById('orderDetailsModal');
const closeOrderModal     = document.getElementById('closeOrderModal');
const closeOrderModalBtn  = document.getElementById('closeOrderModalBtn');
const orderSummary        = document.getElementById('orderSummary');
const orderItemsBody      = document.getElementById('orderItemsBody');
const newOrderStatus      = document.getElementById('newOrderStatus');
const updateOrderStatusBtn = document.getElementById('updateOrderStatusBtn');

// ────────────────────────────────────────────────
// State & Constants
// ────────────────────────────────────────────────
let allOrders = [];
let currentOrderId = null;

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

/**
 * Format date to Tanzanian readable format
 * @param {string} dateStr - ISO date string
 * @returns {string}
 */
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Format price to TZS display
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
    ordersTableBody.closest('table').style.display = show ? 'none' : 'table';
}

/**
 * Show/hide empty state
 * @param {boolean} show
 */
function toggleEmptyState(show) {
    emptyOrders.style.display = show ? 'block' : 'none';
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

    document.querySelector('.content-section').prepend(msgEl);

    setTimeout(() => msgEl.remove(), 5000);
}

// ────────────────────────────────────────────────
// Render Functions
// ────────────────────────────────────────────────

/**
 * Create order row element
 * @param {Object} order
 * @returns {HTMLElement}
 */
function createOrderRow(order) {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td>#${order.id}</td>
        <td>${order.customer_name || 'Guest'}</td>
        <td>${formatPrice(order.total_amount)}</td>
        <td>
            <span class="status-badge status-${order.status}">
                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
            </span>
        </td>
        <td>${formatDate(order.created_at)}</td>
        <td class="actions">
            <button class="btn btn-sm btn-view" data-id="${order.id}">
                <i class="fas fa-eye"></i> View
            </button>
            <select class="status-select" data-id="${order.id}">
                <option value="pending" ${order.status === 'pending' ? 'selected' : ''}>Pending</option>
                <option value="preparing" ${order.status === 'preparing' ? 'selected' : ''}>Preparing</option>
                <option value="delivering" ${order.status === 'delivering' ? 'selected' : ''}>Delivering</option>
                <option value="completed" ${order.status === 'completed' ? 'selected' : ''}>Completed</option>
                <option value="cancelled" ${order.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
            </select>
        </td>
    `;

    // Status change handler
    const statusSelect = row.querySelector('.status-select');
    statusSelect.addEventListener('change', (e) => {
        updateOrderStatus(order.id, e.target.value);
    });

    // View details handler
    row.querySelector('.btn-view').addEventListener('click', () => {
        loadOrderDetails(order.id);
    });

    return row;
}

/**
 * Render orders table
 * @param {Array} orders
 */
function renderOrders(orders) {
    ordersTableBody.innerHTML = '';

    if (orders.length === 0) {
        toggleEmptyState(true);
        return;
    }

    toggleEmptyState(false);

    orders.forEach(order => {
        const row = createOrderRow(order);
        ordersTableBody.appendChild(row);
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

        currentOrderId = orderId;

        // Populate summary
        orderSummary.innerHTML = `
            <div class="order-detail-row">
                <strong>Order ID:</strong> #${order.id}
            </div>
            <div class="order-detail-row">
                <strong>Customer:</strong> ${order.customer_name || 'Guest'}
            </div>
            <div class="order-detail-row">
                <strong>Date:</strong> ${formatDate(order.created_at)}
            </div>
            <div class="order-detail-row">
                <strong>Status:</strong> 
                <span class="status-badge status-${order.status}">
                    ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                </span>
            </div>
            <div class="order-detail-row">
                <strong>Total:</strong> ${formatPrice(order.total_amount)}
            </div>
            <div class="order-detail-row">
                <strong>Delivery Address:</strong> 
                ${order.street || 'N/A'}, ${order.city || ''}, 
                ${order.region || ''} ${order.zip || ''}
            </div>
        `;

        // Populate items
        orderItemsBody.innerHTML = '';

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
                orderItemsBody.appendChild(row);
            });
        } else {
            orderItemsBody.innerHTML = `
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

/**
 * Update order status
 * @param {number} orderId
 * @param {string} newStatus
 */
async function updateOrderStatus(orderId, newStatus) {
    try {
        const response = await patch(
            `${API_BASE_URL}${ENDPOINTS.ORDERS.UPDATE_STATUS(orderId)}`,
            { status: newStatus }
        );

        if (response.success) {
            showMessage(`Order status updated to ${newStatus}`, 'success');
            loadUserOrders(); // Refresh table
        } else {
            showMessage(response.message || 'Failed to update status', 'error');
        }

    } catch (error) {
        console.error('Update status error:', error);
        showMessage('Failed to update status', 'error');
    }
}

// ────────────────────────────────────────────────
// Main Load Function
// ────────────────────────────────────────────────

async function loadAllOrders() {
    const token = getToken();
    if (!token) {
        showMessage('Authentication required. Please login.', 'error');
        return;
    }

    try {
        toggleLoading(true);

        const orders = await get(`${API_BASE_URL}${ENDPOINTS.ORDERS.GET_ALL}`);

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
    loadAllOrders();

    // Status filter change
    if (statusFilter) {
        statusFilter.addEventListener('change', (e) => {
            const filterStatus = e.target.value;
            const filtered = filterStatus === 'all'
                ? allOrders
                : allOrders.filter(o => o.status === filterStatus);
            renderOrders(filtered);
        });
    }

    // Search input with debounce
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const term = searchInput.value.toLowerCase().trim();
                const filtered = allOrders.filter(order =>
                    String(order.id).includes(term) ||
                    (order.customer_name && order.customer_name.toLowerCase().includes(term)) ||
                    order.status.toLowerCase().includes(term)
                );
                renderOrders(filtered);
            }, 300);
        });
    }

    // Close modal
    if (closeOrderModal) {
        closeOrderModal.addEventListener('click', () => {
            orderDetailsModal.style.display = 'none';
        });
    }

    if (closeOrderModalBtn) {
        closeOrderModalBtn.addEventListener('click', () => {
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