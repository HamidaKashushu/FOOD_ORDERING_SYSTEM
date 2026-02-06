/**
 * Food Ordering System - Cart Page Script
 * Manages cart display, quantity updates, item removal,
 * cart clearing, total calculation, and checkout initiation.
 *
 * Dependencies:
 * - js/utils/fetch.js
 * - js/utils/storage.js (optional for cart count)
 * - js/config/api.js
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { get, post, patch, del } from '../utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { getToken } from '../auth/auth.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const cartItemsContainer = document.getElementById('cartItemsContainer');
const cartSummary        = document.getElementById('cartSummary');
const subtotalDisplay    = document.getElementById('subtotal');
const grandTotalDisplay  = document.getElementById('grandTotal');
const deliveryFeeDisplay = document.getElementById('deliveryFee');
const checkoutBtn        = document.getElementById('checkoutBtn');
const clearCartBtn       = document.getElementById('clearCartBtn');
const cartLoading        = document.getElementById('cartLoading');
const emptyCartMessage   = document.getElementById('emptyCartMessage');
const messageDisplay     = document.getElementById('cartMessage');

// ────────────────────────────────────────────────
// Constants
// ────────────────────────────────────────────────
const DELIVERY_FEE = 2000; // TZS - fixed for simplicity

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

/**
 * Format price to Tanzanian Shilling display
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
 * Show message to user (success/error)
 * @param {string} message
 * @param {string} type - 'success' | 'error'
 */
function showMessage(message, type = 'error') {
    if (!messageDisplay) return;

    messageDisplay.textContent = message;
    messageDisplay.className = `message ${type}`;
    messageDisplay.style.display = 'block';

    if (type === 'success') {
        setTimeout(() => {
            messageDisplay.style.display = 'none';
        }, 4000);
    }
}

/**
 * Create cart item HTML element
 * @param {Object} item
 * @returns {HTMLElement}
 */
function createCartItemElement(item) {
    const row = document.createElement('div');
    row.className = 'cart-item';
    row.dataset.productId = item.product_id;

    row.innerHTML = `
        <div class="cart-item-image">
            <img src="${item.image_url || '/assets/images/placeholder-product.jpg'}" 
                 alt="${item.name}" 
                 loading="lazy">
        </div>

        <div class="cart-item-info">
            <h4>${item.name}</h4>
            <p class="category">${item.category_name || 'Uncategorized'}</p>
            <div class="price-per-unit">${formatPrice(item.price_at_time)}</div>
        </div>

        <div class="cart-item-quantity">
            <button class="qty-btn decrease" data-product-id="${item.product_id}">-</button>
            <input type="number" class="qty-input" value="${item.quantity}" min="1" data-product-id="${item.product_id}">
            <button class="qty-btn increase" data-product-id="${item.product_id}">+</button>
        </div>

        <div class="cart-item-subtotal">
            ${formatPrice(item.subtotal)}
        </div>

        <button class="btn-remove" data-product-id="${item.product_id}">
            <i class="fas fa-trash"></i>
        </button>
    `;

    // Quantity controls
    const qtyInput = row.querySelector('.qty-input');
    const decreaseBtn = row.querySelector('.decrease');
    const increaseBtn = row.querySelector('.increase');

    decreaseBtn.addEventListener('click', () => {
        const newQty = Math.max(1, parseInt(qtyInput.value) - 1);
        qtyInput.value = newQty;
        updateItemQuantity(item.product_id, newQty);
    });

    increaseBtn.addEventListener('click', () => {
        const newQty = parseInt(qtyInput.value) + 1;
        qtyInput.value = newQty;
        updateItemQuantity(item.product_id, newQty);
    });

    qtyInput.addEventListener('change', () => {
        const newQty = Math.max(1, parseInt(qtyInput.value));
        qtyInput.value = newQty;
        updateItemQuantity(item.product_id, newQty);
    });

    // Remove button
    row.querySelector('.btn-remove').addEventListener('click', () => {
        removeItem(item.product_id);
    });

    return row;
}

/**
 * Render cart items and update totals
 * @param {Array} items
 */
function renderCart(items) {
    cartItemsContainer.innerHTML = '';

    if (items.length === 0) {
        emptyCartMessage.style.display = 'block';
        cartSummary.style.display = 'none';
        return;
    }

    emptyCartMessage.style.display = 'none';
    cartSummary.style.display = 'block';

    let subtotal = 0;

    items.forEach(item => {
        const element = createCartItemElement(item);
        cartItemsContainer.appendChild(element);
        subtotal += parseFloat(item.subtotal);
    });

    // Update totals
    subtotalDisplay.textContent = formatPrice(subtotal);
    const grandTotal = subtotal + DELIVERY_FEE;
    grandTotalDisplay.textContent = formatPrice(grandTotal);
}

// ────────────────────────────────────────────────
// API Functions
// ────────────────────────────────────────────────

/**
 * Load current user's cart
 */
async function loadCart() {
    const token = getToken();
    if (!token) {
        showMessage('Please login to view your cart', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
        return;
    }

    try {
        cartLoading.style.display = 'block';
        const cartData = await get(`${API_BASE_URL}${ENDPOINTS.CART.GET_CART}`);

        renderCart(cartData.items || []);

    } catch (error) {
        console.error('Failed to load cart:', error);
        showMessage('Failed to load cart. Please try again.', 'error');
    } finally {
        cartLoading.style.display = 'none';
    }
}

/**
 * Update item quantity
 * @param {number} productId
 * @param {number} quantity
 */
async function updateItemQuantity(productId, quantity) {
    try {
        const response = await post(
            `${API_BASE_URL}${ENDPOINTS.CART.UPDATE_ITEM}`,
            {
                product_id: productId,
                quantity: quantity
            }
        );

        if (response.success) {
            loadCart(); // Refresh entire cart
        } else {
            showMessage(response.message || 'Failed to update quantity', 'error');
        }

    } catch (error) {
        console.error('Update quantity error:', error);
        showMessage('Failed to update quantity', 'error');
    }
}

/**
 * Remove item from cart
 * @param {number} productId
 */
async function removeItem(productId) {
    if (!confirm('Remove this item from cart?')) return;

    try {
        const response = await del(
            `${API_BASE_URL}${ENDPOINTS.CART.REMOVE_ITEM}`,
            {
                product_id: productId
            }
        );

        if (response.success) {
            showMessage('Item removed from cart', 'success');
            loadCart();
        } else {
            showMessage(response.message || 'Failed to remove item', 'error');
        }

    } catch (error) {
        console.error('Remove item error:', error);
        showMessage('Failed to remove item', 'error');
    }
}

/**
 * Clear entire cart
 */
async function clearCart() {
    if (!confirm('Clear all items from your cart?')) return;

    try {
        const response = await del(`${API_BASE_URL}${ENDPOINTS.CART.CLEAR_CART}`);

        if (response.success) {
            showMessage('Cart cleared successfully', 'success');
            loadCart();
        } else {
            showMessage(response.message || 'Failed to clear cart', 'error');
        }

    } catch (error) {
        console.error('Clear cart error:', error);
        showMessage('Failed to clear cart', 'error');
    }
}

/**
 * Proceed to checkout
 */
function proceedToCheckout() {
    const items = document.querySelectorAll('.cart-item');
    if (items.length === 0) {
        showMessage('Your cart is empty', 'error');
        return;
    }

    window.location.href = 'checkout.html';
}

// ────────────────────────────────────────────────
// Event Listeners & Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load cart on page load
    loadCart();

    // Checkout button
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', proceedToCheckout);
    }

    // Clear cart button
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', clearCart);
    }

    // Optional: Update cart count in header (if exists)
    updateCartCount();
});