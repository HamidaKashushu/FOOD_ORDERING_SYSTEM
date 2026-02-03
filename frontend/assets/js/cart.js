// backend/js/cart.js
// Cart management API operations for Food Ordering System frontend

import { API_BASE } from './config.js';
import { showMessage, formatPrice } from './utils.js';
import { getCurrentUser } from './auth.js';

/**
 * Fetch user's cart with items and total
 * @param {number} userId - User ID
 * @returns {Promise<object>} - { success, message, data: { cart_id, items[], total } }
 */
async function getCart(userId) {
    if (!Number.isInteger(userId) || userId <= 0) {
        showMessage('Invalid user ID', 'error');
        throw new Error('Invalid user ID');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=cart&action=get&user_id=${userId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to load cart', 'error');
            throw new Error(data.message || 'Failed to fetch cart');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error while fetching cart', 'error');
        throw error;
    }
}

/**
 * Add item to user's cart
 * @param {number} userId - User ID
 * @param {number} foodId - Food ID
 * @param {number} [quantity=1] - Quantity to add
 * @returns {Promise<object>}
 */
async function addItemToCart(userId, foodId, quantity = 1) {
    if (!Number.isInteger(userId) || userId <= 0 || !Number.isInteger(foodId) || foodId <= 0 || quantity <= 0) {
        showMessage('Invalid cart item details', 'error');
        throw new Error('Invalid cart item details');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=cart&action=add`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                user_id: userId,
                food_id: foodId,
                quantity: Number(quantity)
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to add item to cart', 'error');
            throw new Error(data.message || 'Add to cart failed');
        }

        showMessage('Item added to cart successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error adding to cart', 'error');
        throw error;
    }
}

/**
 * Update cart item quantity
 * @param {number} cartItemId - Cart item ID
 * @param {number} quantity - New quantity (≤0 removes item)
 * @returns {Promise<object>}
 */
async function updateCartItem(cartItemId, quantity) {
    if (!Number.isInteger(cartItemId) || cartItemId <= 0 || !Number.isInteger(quantity)) {
        showMessage('Invalid cart item or quantity', 'error');
        throw new Error('Invalid cart item or quantity');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=cart&action=update&id=${cartItemId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                quantity: Number(quantity)
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to update cart item', 'error');
            throw new Error(data.message || 'Update failed');
        }

        const actionMsg = quantity <= 0 ? 'Item removed from cart' : 'Cart item updated';
        showMessage(actionMsg, 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error updating cart', 'error');
        throw error;
    }
}

/**
 * Remove single item from cart
 * @param {number} cartItemId - Cart item ID
 * @returns {Promise<object>}
 */
async function removeCartItem(cartItemId) {
    if (!Number.isInteger(cartItemId) || cartItemId <= 0) {
        showMessage('Invalid cart item ID', 'error');
        throw new Error('Invalid cart item ID');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=cart&action=remove&id=${cartItemId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to remove item', 'error');
            throw new Error(data.message || 'Remove failed');
        }

        showMessage('Item removed from cart', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error removing item', 'error');
        throw error;
    }
}

/**
 * Clear all items from user's cart
 * @param {number} userId - User ID
 * @returns {Promise<object>}
 */
async function clearCart(userId) {
    if (!Number.isInteger(userId) || userId <= 0) {
        showMessage('Invalid user ID', 'error');
        throw new Error('Invalid user ID');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=cart&action=clear&user_id=${userId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to clear cart', 'error');
            throw new Error(data.message || 'Clear cart failed');
        }

        showMessage('Cart cleared successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error clearing cart', 'error');
        throw error;
    }
}

/**
 * Calculate total amount from cart data (client-side validation)
 * @param {object} cartData - Cart response data { items: [], total: number }
 * @returns {number} Total amount
 */
function getCartTotal(cartData) {
    if (!cartData || !Array.isArray(cartData.items)) {
        return 0;
    }

    return cartData.items.reduce((total, item) => {
        return total + (Number(item.price || 0) * Number(item.quantity || 0));
    }, 0);
}

/**
 * Get current user's cart (convenience wrapper)
 * @returns {Promise<object>}
 */
async function getCurrentUserCart() {
    const user = getCurrentUser();
    if (!user) {
        throw new Error('User not logged in');
    }
    return getCart(user.id);
}

// Export functions (use with <script type="module">)
export {
    getCart,
    addItemToCart,
    updateCartItem,
    removeCartItem,
    clearCart,
    getCartTotal,
    getCurrentUserCart
};

// Optional global fallback for non-module scripts
window.CartAPI = {
    getCart,
    addItemToCart,
    updateCartItem,
    removeCartItem,
    clearCart,
    getCartTotal,
    getCurrentUserCart
};