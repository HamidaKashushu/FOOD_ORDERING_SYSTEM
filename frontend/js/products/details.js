/**
 * Food Ordering System - Product Details Page Script
 * Handles fetching and displaying individual product information,
 * quantity selection, and adding items to cart.
 *
 * Dependencies:
 * - js/utils/fetch.js
 * - js/utils/storage.js (for cart count update)
 * - js/config/api.js
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { get, post } from '../utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { getToken } from '../auth/auth.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const productImage       = document.getElementById('productImage');
const productName        = document.getElementById('productName');
const productCategory    = document.getElementById('productCategory');
const productPrice       = document.getElementById('productPrice');
const productDescription = document.getElementById('productDescription');
const productStock       = document.getElementById('productStock');
const quantityInput      = document.getElementById('quantityInput');
const addToCartBtn       = document.getElementById('addToCartBtn');
const messageDisplay     = document.getElementById('messageDisplay');
const loadingContainer   = document.getElementById('loadingContainer');
const productContent     = document.getElementById('productContent');

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

    // Auto-hide success messages after 4 seconds
    if (type === 'success') {
        setTimeout(() => {
            messageDisplay.style.display = 'none';
        }, 4000);
    }
}

/**
 * Extract product ID from URL query string
 * @returns {number|null}
 */
function getProductIdFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id') || params.get('product_id');
    return id ? parseInt(id, 10) : null;
}

/**
 * Load and display product details
 * @param {number} productId
 */
async function loadProductDetails(productId) {
    if (!productId) {
        showMessage('Invalid product ID', 'error');
        productContent.style.display = 'none';
        return;
    }

    try {
        loadingContainer.style.display = 'block';
        productContent.style.display = 'none';

        const product = await get(
            `${API_BASE_URL}${ENDPOINTS.PRODUCTS.GET_ONE(productId)}`
        );

        if (!product) {
            showMessage('Product not found', 'error');
            return;
        }

        // Populate DOM elements
        productImage.src = product.image || '/assets/images/placeholder-product.jpg';
        productImage.alt = product.name;
        productName.textContent = product.name;
        productCategory.textContent = product.category_name || 'Uncategorized';
        productPrice.textContent = formatPrice(product.price);
        productDescription.textContent = product.description || 'No description available';
        productStock.textContent = product.stock > 0 
            ? `${product.stock} in stock` 
            : 'Out of stock';

        // Disable add to cart if out of stock
        if (product.stock <= 0 || product.status !== 'available') {
            addToCartBtn.disabled = true;
            addToCartBtn.textContent = 'Out of Stock';
            addToCartBtn.classList.add('btn-disabled');
        }

    } catch (error) {
        console.error('Failed to load product:', error);
        showMessage('Failed to load product details. Please try again.', 'error');
    } finally {
        loadingContainer.style.display = 'none';
        productContent.style.display = 'block';
    }
}

/**
 * Add product to cart with selected quantity
 * @param {number} productId
 */
async function addToCart(productId) {
    const token = getToken();
    if (!token) {
        showMessage('Please login to add items to cart', 'error');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 1500);
        return;
    }

    const quantity = parseInt(quantityInput.value, 10);
    if (isNaN(quantity) || quantity < 1) {
        showMessage('Please select a valid quantity', 'error');
        return;
    }

    try {
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = '<span class="loading-spinner"></span> Adding...';

        const response = await post(
            `${API_BASE_URL}${ENDPOINTS.CART.ADD_ITEM}`,
            {
                product_id: productId,
                quantity: quantity
            }
        );

        if (response.success) {
            showMessage(`Added ${quantity} item(s) to cart!`, 'success');
            // Optional: update cart count in header
            updateCartCount();
        } else {
            showMessage(response.message || 'Failed to add to cart', 'error');
        }

    } catch (error) {
        console.error('Add to cart error:', error);
        showMessage('Failed to add item. Please try again.', 'error');
    } finally {
        addToCartBtn.disabled = false;
        addToCartBtn.textContent = 'Add to Cart';
    }
}

/**
 * Update cart count in header (optional enhancement)
 */
async function updateCartCount() {
    try {
        const cart = await get(`${API_BASE_URL}${ENDPOINTS.CART.GET_CART}`);
        const countElement = document.getElementById('cart-count');
        if (countElement) {
            countElement.textContent = cart.items?.length || 0;
        }
    } catch (error) {
        console.error('Failed to update cart count:', error);
    }
}

// ────────────────────────────────────────────────
// Event Listeners & Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    const productId = getProductIdFromUrl();

    if (!productId) {
        showMessage('Invalid product ID in URL', 'error');
        return;
    }

    // Load product details
    loadProductDetails(productId);

    // Add to cart button
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', () => addToCart(productId));
    }

    // Quantity controls (optional + / - buttons)
    const decreaseBtn = document.getElementById('decreaseQty');
    const increaseBtn = document.getElementById('increaseQty');

    if (decreaseBtn && increaseBtn && quantityInput) {
        decreaseBtn.addEventListener('click', () => {
            const qty = parseInt(quantityInput.value, 10);
            if (qty > 1) {
                quantityInput.value = qty - 1;
            }
        });

        increaseBtn.addEventListener('click', () => {
            const qty = parseInt(quantityInput.value, 10);
            quantityInput.value = qty + 1;
        });
    }
});