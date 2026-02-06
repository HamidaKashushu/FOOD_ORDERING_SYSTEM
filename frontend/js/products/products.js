/**
 * Food Ordering System - Products Page Script
 * Handles fetching, filtering, searching, and displaying products.
 * Integrates with backend API endpoints for products and categories.
 *
 * Features:
 * - Dynamic product grid rendering
 * - Category filtering
 * - Real-time search with debounce
 * - Add to cart functionality
 * - Loading states and error handling
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
const productsGrid      = document.getElementById('productsGrid');
const categoryFilter    = document.getElementById('categoryFilter');
const searchInput       = document.getElementById('searchInput');
const noResults         = document.getElementById('noResults');
const productsLoading   = document.getElementById('productsLoading');

// ────────────────────────────────────────────────
// State & Constants
// ────────────────────────────────────────────────
let allProducts = [];
let currentCategory = 'all';
let searchTimeout = null;
const DEBOUNCE_DELAY = 300; // ms

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
 * Create product card HTML element
 * @param {Object} product
 * @returns {HTMLElement}
 */
function createProductCard(product) {
    const card = document.createElement('div');
    card.className = 'product-card';
    card.dataset.category = product.category_id;

    card.innerHTML = `
        <div class="product-image">
            <img src="${product.image || '/assets/images/placeholder-product.jpg'}" 
                 alt="${product.name}" 
                 loading="lazy">
        </div>
        <div class="product-info">
            <h3 class="product-name">${product.name}</h3>
            <p class="product-category">${product.category_name || 'Uncategorized'}</p>
            <div class="product-price">${formatPrice(product.price)}</div>
            <button class="btn btn-primary btn-add-to-cart" 
                    data-product-id="${product.id}">
                Add to Cart
            </button>
        </div>
    `;

    // Add to cart handler
    const addBtn = card.querySelector('.btn-add-to-cart');
    addBtn.addEventListener('click', () => addToCart(product.id));

    return card;
}

/**
 * Render products to the grid
 * @param {Array} products
 */
function renderProducts(products) {
    productsGrid.innerHTML = '';
    noResults.style.display = 'none';

    if (products.length === 0) {
        noResults.style.display = 'block';
        return;
    }

    products.forEach(product => {
        const card = createProductCard(product);
        productsGrid.appendChild(card);
    });
}

/**
 * Filter products by current category and search term
 */
function filterProducts() {
    let filtered = [...allProducts];

    // Category filter
    if (currentCategory !== 'all') {
        filtered = filtered.filter(p => p.category_id == currentCategory);
    }

    // Search filter (already debounced)
    const term = searchInput.value.trim().toLowerCase();
    if (term) {
        filtered = filtered.filter(p =>
            p.name.toLowerCase().includes(term) ||
            (p.description && p.description.toLowerCase().includes(term))
        );
    }

    renderProducts(filtered);
}

// ────────────────────────────────────────────────
// API Functions
// ────────────────────────────────────────────────

/**
 * Fetch all categories and populate filter
 */
async function loadCategories() {
    try {
        const categories = await get(`${API_BASE_URL}${ENDPOINTS.CATEGORIES.GET_ALL}`);

        // Clear existing options (except "All")
        categoryFilter.innerHTML = '<option value="all">All Categories</option>';

        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            categoryFilter.appendChild(option);
        });
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
}

/**
 * Fetch all products
 */
async function loadProducts() {
    try {
        productsLoading.style.display = 'block';
        productsGrid.style.display = 'none';

        const products = await get(`${API_BASE_URL}${ENDPOINTS.PRODUCTS.GET_ALL}`);
        allProducts = products || [];
        filterProducts();

    } catch (error) {
        console.error('Failed to load products:', error);
        productsGrid.innerHTML = `
            <div class="error-message">
                Failed to load products. Please try again later.
            </div>
        `;
    } finally {
        productsLoading.style.display = 'none';
        productsGrid.style.display = 'grid';
    }
}

/**
 * Add product to cart
 * @param {number} productId
 */
async function addToCart(productId) {
    const token = getToken();
    if (!token) {
        alert('Please login to add items to cart');
        window.location.href = 'login.html';
        return;
    }

    try {
        const response = await post(
            `${API_BASE_URL}${ENDPOINTS.CART.ADD_ITEM}`,
            {
                product_id: productId,
                quantity: 1
            }
        );

        if (response.success) {
            alert('Item added to cart!');
            // Optional: update cart count badge
            updateCartCount();
        } else {
            alert(response.message || 'Failed to add item');
        }

    } catch (error) {
        console.error('Add to cart error:', error);
        alert('Failed to add item. Please try again.');
    }
}

/**
 * Update cart count badge (optional enhancement)
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
// Event Listeners
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load initial data
    loadCategories();
    loadProducts();

    // Category filter change
    if (categoryFilter) {
        categoryFilter.addEventListener('change', (e) => {
            currentCategory = e.target.value;
            filterProducts();
        });
    }

    // Search input with debounce
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterProducts, DEBOUNCE_DELAY);
        });
    }

    // Optional: Search on Enter key
    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                filterProducts();
            }
        });
    }
});