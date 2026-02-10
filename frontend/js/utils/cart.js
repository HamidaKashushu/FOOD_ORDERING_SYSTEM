/**
 * Food Ordering System - Shared Cart Utilities
 * Exports functions for common cart operations like "Add to Cart".
 */

import { post } from './fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { showToast, updateCartCount } from '../main.js';
import { isAuthenticated } from '../auth/auth.js';

/**
 * Add product to cart
 * @param {number} productId 
 * @param {number} quantity 
 */
export async function addToCart(productId, quantity = 1) {
    if (!isAuthenticated()) {
        showToast('Please login to add items to cart', 'info');
        // Optional: redirect to login or show modal
        setTimeout(() => window.location.href = 'login.html', 1000);
        return;
    }

    try {
        const response = await post(
            `${API_BASE_URL}${ENDPOINTS.CART.ADD_ITEM}`,
            {
                product_id: productId,
                quantity: quantity
            }
        );

        if (response.success) {
            showToast('Item added to cart', 'success');
            updateCartCount(); // Update the badge
        } else {
            showToast(response.message || 'Failed to add item', 'error');
        }

    } catch (error) {
        console.error('Add to cart error:', error);
        showToast('Failed to connect to server', 'error');
    }
}
