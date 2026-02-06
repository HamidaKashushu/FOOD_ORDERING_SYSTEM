/**
 * Food Ordering System - Admin Products Management Script
 * Handles CRUD operations and status toggling for products in admin panel.
 *
 * Features:
 * - Fetch and display all products in responsive table
 * - Add new product with image upload support
 * - Edit existing product details
 * - Delete products with confirmation
 * - Toggle product availability status
 * - Dynamic feedback with success/error messages
 * - Category dropdown population
 *
 * Dependencies:
 * - js/utils/fetch.js
 * - js/utils/storage.js (for auth token)
 * - js/config/api.js
 * - js/utils/validator.js (for form validation)
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { get, post, put, del } from '../utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { getToken } from '../auth/auth.js';
import { isRequired, isNumber, minLength } from '../utils/validator.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const productsTableBody   = document.getElementById('productsBody');
const addProductBtn       = document.getElementById('addProductBtn');
const productModal        = document.getElementById('productModal');
const modalTitle          = document.getElementById('modalTitle');
const productForm         = document.getElementById('productForm');
const productIdInput      = document.getElementById('productId');
const productNameInput    = document.getElementById('productName');
const productDescInput    = document.getElementById('productDescription');
const productPriceInput   = document.getElementById('productPrice');
const productCategorySelect = document.getElementById('productCategory');
const productStockInput   = document.getElementById('productStock');
const productStatusSelect = document.getElementById('productStatus');
const productImageInput   = document.getElementById('productImage');
const imagePreview        = document.getElementById('imagePreview');
const saveProductBtn      = document.getElementById('saveProductBtn');
const cancelProductBtn    = document.getElementById('cancelProductBtn');
const closeModalBtn       = document.getElementById('closeModal');
const productMessage      = document.getElementById('productMessage');
const emptyProducts       = document.getElementById('emptyProducts');
const addFirstProductBtn  = document.getElementById('addFirstProductBtn');

// ────────────────────────────────────────────────
// State & Constants
// ────────────────────────────────────────────────
let currentProducts = [];
let editingProductId = null;

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

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
 * Show message in modal
 * @param {string} msg
 * @param {string} type - 'success' | 'error'
 */
function showProductMessage(msg, type = 'error') {
    productMessage.textContent = msg;
    productMessage.className = `message ${type}`;
    productMessage.style.display = msg ? 'block' : 'none';
}

/**
 * Reset form to add mode
 */
function resetProductForm() {
    editingProductId = null;
    modalTitle.textContent = 'Add New Product';
    productForm.reset();
    productIdInput.value = '';
    imagePreview.innerHTML = '<span>Upload Image</span>';
    productMessage.style.display = 'none';
    saveProductBtn.disabled = false;
}

/**
 * Open modal for add/edit
 * @param {Object|null} product - Product data for edit mode
 */
function openProductModal(product = null) {
    resetProductForm();

    if (product) {
        editingProductId = product.id;
        modalTitle.textContent = 'Edit Product';
        productNameInput.value = product.name || '';
        productDescInput.value = product.description || '';
        productPriceInput.value = product.price || '';
        productStockInput.value = product.stock || 0;
        productStatusSelect.value = product.status || 'available';

        // Set category
        if (product.category_id) {
            productCategorySelect.value = product.category_id;
        }

        // Show existing image preview
        if (product.image) {
            imagePreview.innerHTML = `<img src="${product.image}" alt="Preview">`;
        }
    }

    productModal.style.display = 'block';
}

/**
 * Close product modal
 */
function closeProductModal() {
    productModal.style.display = 'none';
    resetProductForm();
}

// ────────────────────────────────────────────────
// API Functions
// ────────────────────────────────────────────────

/**
 * Load all products and render table
 */
async function loadProducts() {
    try {
        const products = await get(`${API_BASE_URL}${ENDPOINTS.PRODUCTS.GET_ALL}`);
        currentProducts = products || [];

        productsTableBody.innerHTML = '';

        if (currentProducts.length === 0) {
            emptyProducts.style.display = 'block';
            return;
        }

        emptyProducts.style.display = 'none';

        currentProducts.forEach(product => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <img src="${product.image || '/assets/images/placeholder-product.jpg'}" 
                         alt="${product.name}" class="product-thumb">
                </td>
                <td>${product.name}</td>
                <td>${product.category_name || 'Uncategorized'}</td>
                <td>${formatPrice(product.price)}</td>
                <td>${product.stock || 0}</td>
                <td>
                    <span class="status-badge status-${product.status}">
                        ${product.status === 'available' ? 'Available' : 'Unavailable'}
                    </span>
                </td>
                <td class="actions">
                    <button class="btn btn-sm btn-edit" data-id="${product.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-toggle" data-id="${product.id}">
                        ${product.status === 'available' ? 'Deactivate' : 'Activate'}
                    </button>
                    <button class="btn btn-sm btn-delete" data-id="${product.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            productsTableBody.appendChild(row);
        });

    } catch (error) {
        console.error('Failed to load products:', error);
        productsTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center error">
                    Failed to load products. Please refresh the page.
                </td>
            </tr>
        `;
    }
}

/**
 * Load categories into dropdown
 */
async function loadCategories() {
    try {
        const categories = await get(`${API_BASE_URL}${ENDPOINTS.CATEGORIES.GET_ALL}`);

        productCategorySelect.innerHTML = '<option value="">Select Category</option>';

        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            productCategorySelect.appendChild(option);
        });

    } catch (error) {
        console.error('Failed to load categories:', error);
        showProductMessage('Failed to load categories', 'error');
    }
}

/**
 * Save product (add or edit)
 * @param {Event} event
 */
async function saveProduct(event) {
    event.preventDefault();

    const formData = new FormData(productForm);
    const productData = Object.fromEntries(formData);

    // Client-side validation
    const validations = [
        { check: isRequired(productData.name), msg: 'Product name is required' },
        { check: isRequired(productData.price), msg: 'Price is required' },
        { check: isNumber(productData.price), msg: 'Price must be a valid number' },
        { check: isRequired(productData.category_id), msg: 'Category is required' }
    ];

    for (const v of validations) {
        if (!v.check.valid) {
            showProductMessage(v.msg, 'error');
            return;
        }
    }

    try {
        saveProductBtn.disabled = true;
        saveProductBtn.querySelector('.btn-text').style.display = 'none';
        saveProductBtn.querySelector('.btn-loading').style.display = 'inline';

        let response;

        if (editingProductId) {
            // Update existing product
            response = await put(
                `${API_BASE_URL}${ENDPOINTS.PRODUCTS.UPDATE(editingProductId)}`,
                productData
            );
        } else {
            // Create new product
            response = await post(
                `${API_BASE_URL}${ENDPOINTS.PRODUCTS.CREATE}`,
                productData
            );
        }

        if (response.success) {
            showProductMessage(
                editingProductId ? 'Product updated successfully' : 'Product added successfully',
                'success'
            );
            closeProductModal();
            loadProducts();
        } else {
            showProductMessage(response.message || 'Operation failed', 'error');
        }

    } catch (error) {
        console.error('Product save error:', error);
        showProductMessage('Failed to save product. Please try again.', 'error');
    } finally {
        saveProductBtn.disabled = false;
        saveProductBtn.querySelector('.btn-text').style.display = 'inline';
        saveProductBtn.querySelector('.btn-loading').style.display = 'none';
    }
}

/**
 * Delete product with confirmation
 * @param {number} productId
 */
async function deleteProduct(productId) {
    if (!confirm('Are you sure you want to delete this product?')) return;

    try {
        const response = await del(
            `${API_BASE_URL}${ENDPOINTS.PRODUCTS.DELETE(productId)}`
        );

        if (response.success) {
            showProductMessage('Product deleted successfully', 'success');
            loadProducts();
        } else {
            showProductMessage(response.message || 'Failed to delete product', 'error');
        }

    } catch (error) {
        console.error('Delete product error:', error);
        showProductMessage('Failed to delete product', 'error');
    }
}

/**
 * Toggle product status (available/unavailable)
 * @param {number} productId
 * @param {string} currentStatus
 */
async function toggleProductStatus(productId, currentStatus) {
    const newStatus = currentStatus === 'available' ? 'unavailable' : 'available';

    try {
        const response = await patch(
            `${API_BASE_URL}${ENDPOINTS.PRODUCTS.STATUS(productId)}`,
            { status: newStatus }
        );

        if (response.success) {
            showProductMessage(
                `Product status changed to ${newStatus}`,
                'success'
            );
            loadProducts();
        } else {
            showProductMessage(response.message || 'Failed to update status', 'error');
        }

    } catch (error) {
        console.error('Toggle status error:', error);
        showProductMessage('Failed to update status', 'error');
    }
}

// ────────────────────────────────────────────────
// Event Listeners & Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load initial data
    loadProducts();
    loadCategories();

    // Add product button
    addProductBtn.addEventListener('click', () => openProductModal());

    // Add first product button (empty state)
    if (addFirstProductBtn) {
        addFirstProductBtn.addEventListener('click', () => openProductModal());
    }

    // Save product form
    productForm.addEventListener('submit', saveProduct);

    // Cancel/close modal
    cancelProductBtn.addEventListener('click', closeProductModal);
    closeModalBtn.addEventListener('click', closeProductModal);

    // Close modal on outside click
    productModal.addEventListener('click', (e) => {
        if (e.target === productModal) {
            closeProductModal();
        }
    });

    // Image preview
    productImageInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                imagePreview.innerHTML = `<img src="${event.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        }
    });

    // Table action delegation
    productsTableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        const productId = parseInt(target.dataset.id);

        if (target.classList.contains('btn-edit')) {
            const product = currentProducts.find(p => p.id === productId);
            if (product) openProductModal(product);
        } else if (target.classList.contains('btn-delete')) {
            await deleteProduct(productId);
        } else if (target.classList.contains('btn-toggle')) {
            const currentStatus = target.closest('tr').querySelector('.status-badge').classList.contains('status-available')
                ? 'available' : 'unavailable';
            await toggleProductStatus(productId, currentStatus);
        }
    });

    // Search functionality (live filter)
    document.getElementById('searchInput')?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase().trim();
        const filtered = currentProducts.filter(p =>
            p.name.toLowerCase().includes(term) ||
            (p.description && p.description.toLowerCase().includes(term))
        );
        renderProducts(filtered);
    });
});