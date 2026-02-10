/**
 * Food Ordering System - Home Page Script
 * Handles loading featured products and categories for the landing page.
 */

import { get } from './utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from './config/api.js';
import { addToCart } from './utils/cart.js'; // We can use this directly or via event

// DOM Elements
const categoriesGrid = document.getElementById('categoriesGrid');
const featuredGrid = document.getElementById('featuredProducts');

// State
let products = [];

document.addEventListener('DOMContentLoaded', () => {
    loadCategories();
    loadFeaturedProducts();
});

/**
 * Load Categories
 */
async function loadCategories() {
    try {
        const categories = await get(`${API_BASE_URL}${ENDPOINTS.CATEGORIES.GET_ALL}`);
        renderCategories(categories || []);
    } catch (error) {
        console.error('Failed to load categories:', error);
        if (categoriesGrid) categoriesGrid.innerHTML = '<p>Failed to load categories</p>';
    }
}

function renderCategories(categories) {
    if (!categoriesGrid) return;

    // Take first 6 or 8 categories
    const displayCats = categories.slice(0, 8);

    categoriesGrid.innerHTML = displayCats.map(cat => `
        <a href="menu.html?category=${cat.id}" class="category-card">
            <div class="category-icon">
                <i class="fas fa-utensils"></i>
            </div>
            <h3>${cat.name}</h3>
        </a>
    `).join('');
}

/**
 * Load Featured Products
 */
async function loadFeaturedProducts() {
    try {
        // For featured, we'll fetch all and take first 4-8, or random, or specific 'popular' ones
        // In real app, endpoint /products?featured=true
        const allProducts = await get(`${API_BASE_URL}${ENDPOINTS.PRODUCTS.GET_ALL}`);

        if (!allProducts || allProducts.length === 0) {
            if (featuredGrid) featuredGrid.innerHTML = '<p>No products found</p>';
            return;
        }

        // Filter for active and maybe popular, or just slice
        const featured = allProducts
            .filter(p => p.status === 'available')
            .slice(0, 4); // Show top 4

        renderFeatured(featured);

    } catch (error) {
        console.error('Failed to load featured products:', error);
        if (featuredGrid) featuredGrid.innerHTML = '<p>Failed to load products</p>';
    }
}

function renderFeatured(products) {
    if (!featuredGrid) return;

    featuredGrid.innerHTML = products.map(p => `
        <div class="product-card">
            <div class="product-image">
                <img src="${p.image_url || p.image || 'assets/images/placeholder-food.png'}" 
                     alt="${p.name}" 
                     loading="lazy">
                ${p.is_popular ? '<span class="badge badge-popular">Popular</span>' : ''}
            </div>
            <div class="product-info">
                <h3 class="product-name">${p.name}</h3>
                <p class="product-category">${p.category_name || 'Delicious'}</p>
                <div class="product-price">TZS ${parseFloat(p.price).toLocaleString()}</div>
                <button class="btn btn-primary btn-add-to-cart" data-id="${p.id}">
                    Add to Cart
                </button>
            </div>
        </div>
    `).join('');

    // Attach event listeners
    featuredGrid.querySelectorAll('.btn-add-to-cart').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const id = e.target.dataset.id;
            addToCart(parseInt(id), 1);
        });
    });
}
