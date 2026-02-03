// backend/js/orders.js
// Order management API operations for Food Ordering System frontend

import { API_BASE } from './config.js';
import { showMessage } from './utils.js';
import { getCurrentUser } from './auth.js';

/**
 * Place a new order (uses current cart contents on backend)
 * @param {number} userId - User ID
 * @param {string} paymentMethod - e.g. "cash", "mobile", "card"
 * @returns {Promise<object>} Response with order_id on success
 */
async function placeOrder(userId, paymentMethod) {
    if (!Number.isInteger(userId) || userId <= 0) {
        showMessage('Invalid user ID', 'error');
        throw new Error('Invalid user ID');
    }
    if (!paymentMethod || typeof paymentMethod !== 'string' || paymentMethod.trim() === '') {
        showMessage('Payment method is required', 'error');
        throw new Error('Payment method is required');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=orders&action=place`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                user_id: userId,
                payment_method: paymentMethod.trim()
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            const msg = data.message || 'Failed to place order';
            showMessage(msg, 'error');
            throw new Error(msg);
        }

        showMessage(`Order #${data.data.order_id} placed successfully!`, 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error while placing order', 'error');
        throw error;
    }
}

/**
 * Fetch all orders for a specific user
 * @param {number} userId
 * @returns {Promise<object>} { success, message, data: orders[] }
 */
async function getUserOrders(userId) {
    if (!Number.isInteger(userId) || userId <= 0) {
        showMessage('Invalid user ID', 'error');
        throw new Error('Invalid user ID');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=orders&action=user&user_id=${userId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to load orders', 'error');
            throw new Error(data.message || 'Failed to fetch orders');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error while fetching orders', 'error');
        throw error;
    }
}

/**
 * Get detailed information for a single order
 * @param {number} orderId
 * @returns {Promise<object>} Order details including items and payment
 */
async function getOrderDetails(orderId) {
    if (!Number.isInteger(orderId) || orderId <= 0) {
        showMessage('Invalid order ID', 'error');
        throw new Error('Invalid order ID');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=orders&action=single&id=${orderId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Order not found', 'error');
            throw new Error(data.message || 'Failed to fetch order details');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error while fetching order details', 'error');
        throw error;
    }
}

/**
 * Update order status (admin only)
 * @param {number} orderId
 * @param {string} status - e.g. "preparing", "delivered", "cancelled"
 * @returns {Promise<object>}
 */
async function updateOrderStatus(orderId, status) {
    if (!Number.isInteger(orderId) || orderId <= 0) {
        showMessage('Invalid order ID', 'error');
        throw new Error('Invalid order ID');
    }
    if (!status || typeof status !== 'string' || status.trim() === '') {
        showMessage('Status is required', 'error');
        throw new Error('Status is required');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=orders&action=status&id=${orderId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                status: status.trim()
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to update order status', 'error');
            throw new Error(data.message || 'Status update failed');
        }

        showMessage(`Order status updated to "${status}"`, 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error updating order status', 'error');
        throw error;
    }
}

/**
 * Convenience: Place order for currently logged-in user
 * @param {string} paymentMethod
 * @returns {Promise<object>}
 */
async function placeOrderForCurrentUser(paymentMethod) {
    const user = getCurrentUser();
    if (!user || !user.id) {
        showMessage('Please log in to place an order', 'error');
        throw new Error('User not logged in');
    }
    return placeOrder(user.id, paymentMethod);
}

// Export functions (use with <script type="module">)
export {
    placeOrder,
    getUserOrders,
    getOrderDetails,
    updateOrderStatus,
    placeOrderForCurrentUser
};

// Optional global fallback for non-module scripts
window.OrdersAPI = {
    placeOrder,
    getUserOrders,
    getOrderDetails,
    updateOrderStatus,
    placeOrderForCurrentUser
};