/**
 * Food Ordering System - Admin Categories Management Script
 * Handles CRUD operations for product categories in the admin panel.
 *
 * Features:
 * - Fetch and display all categories in responsive table
 * - Add new category with name and description
 * - Edit existing category details
 * - Delete categories with confirmation
 * - Real-time table updates after each operation
 * - Dynamic success/error feedback
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
import { isRequired, minLength } from '../utils/validator.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const categoriesTableBody   = document.getElementById('categoriesBody');
const addCategoryBtn        = document.getElementById('addCategoryBtn');
const categoryModal         = document.getElementById('categoryModal');
const modalTitle            = document.getElementById('modalTitle');
const categoryForm          = document.getElementById('categoryForm');
const categoryIdInput       = document.getElementById('categoryId');
const categoryNameInput     = document.getElementById('categoryName');
const categoryDescInput     = document.getElementById('categoryDescription');
const saveCategoryBtn       = document.getElementById('saveCategoryBtn');
const cancelCategoryBtn     = document.getElementById('cancelCategoryBtn');
const closeModalBtn         = document.getElementById('closeModal');
const categoryMessage       = document.getElementById('categoryMessage');
const emptyCategories       = document.getElementById('emptyCategories');
const addFirstCategoryBtn   = document.getElementById('addFirstCategoryBtn');

// ────────────────────────────────────────────────
// State & Constants
// ────────────────────────────────────────────────
let currentCategories = [];
let editingCategoryId = null;

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

/**
 * Show message in modal
 * @param {string} msg
 * @param {string} type - 'success' | 'error'
 */
function showCategoryMessage(msg, type = 'error') {
    categoryMessage.textContent = msg;
    categoryMessage.className = `message ${type}`;
    categoryMessage.style.display = msg ? 'block' : 'none';
}

/**
 * Reset form to add mode
 */
function resetCategoryForm() {
    editingCategoryId = null;
    modalTitle.textContent = 'Add New Category';
    categoryForm.reset();
    categoryIdInput.value = '';
    categoryMessage.style.display = 'none';
    saveCategoryBtn.disabled = false;
}

/**
 * Open modal for add/edit
 * @param {Object|null} category - Category data for edit mode
 */
function openCategoryModal(category = null) {
    resetCategoryForm();

    if (category) {
        editingCategoryId = category.id;
        modalTitle.textContent = 'Edit Category';
        categoryNameInput.value = category.name || '';
        categoryDescInput.value = category.description || '';
    }

    categoryModal.style.display = 'block';
}

/**
 * Close category modal
 */
function closeCategoryModal() {
    categoryModal.style.display = 'none';
    resetCategoryForm();
}

// ────────────────────────────────────────────────
// API Functions
// ────────────────────────────────────────────────

/**
 * Load all categories and render table
 */
async function loadCategories() {
    try {
        const categories = await get(`${API_BASE_URL}${ENDPOINTS.CATEGORIES.GET_ALL}`);
        currentCategories = categories || [];

        categoriesTableBody.innerHTML = '';

        if (currentCategories.length === 0) {
            emptyCategories.style.display = 'block';
            return;
        }

        emptyCategories.style.display = 'none';

        currentCategories.forEach(category => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${category.name}</td>
                <td>${category.description || '<em>No description</em>'}</td>
                <td>
                    <span class="status-badge status-${category.status}">
                        ${category.status === 'active' ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td class="actions">
                    <button class="btn btn-sm btn-edit" data-id="${category.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-delete" data-id="${category.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            categoriesTableBody.appendChild(row);
        });

    } catch (error) {
        console.error('Failed to load categories:', error);
        categoriesTableBody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center error">
                    Failed to load categories. Please refresh the page.
                </td>
            </tr>
        `;
    }
}

/**
 * Save category (add or edit)
 * @param {Event} event
 */
async function saveCategory(event) {
    event.preventDefault();

    const formData = new FormData(categoryForm);
    const categoryData = Object.fromEntries(formData);

    // Client-side validation
    const nameCheck = isRequired(categoryData.name, 'Category name is required');
    if (!nameCheck.valid) {
        showCategoryMessage(nameCheck.message, 'error');
        return;
    }

    const nameLength = minLength(categoryData.name, 3, 'Category name must be at least 3 characters');
    if (!nameLength.valid) {
        showCategoryMessage(nameLength.message, 'error');
        return;
    }

    try {
        saveCategoryBtn.disabled = true;
        saveCategoryBtn.querySelector('.btn-text').style.display = 'none';
        saveCategoryBtn.querySelector('.btn-loading').style.display = 'inline';

        let response;

        if (editingCategoryId) {
            // Update existing category
            response = await put(
                `${API_BASE_URL}${ENDPOINTS.CATEGORIES.UPDATE(editingCategoryId)}`,
                categoryData
            );
        } else {
            // Create new category
            response = await post(
                `${API_BASE_URL}${ENDPOINTS.CATEGORIES.CREATE}`,
                categoryData
            );
        }

        if (response.success) {
            showCategoryMessage(
                editingCategoryId ? 'Category updated successfully' : 'Category added successfully',
                'success'
            );
            closeCategoryModal();
            loadCategories();
        } else {
            showCategoryMessage(response.message || 'Operation failed', 'error');
        }

    } catch (error) {
        console.error('Category save error:', error);
        showCategoryMessage('Failed to save category. Please try again.', 'error');
    } finally {
        saveCategoryBtn.disabled = false;
        saveCategoryBtn.querySelector('.btn-text').style.display = 'inline';
        saveCategoryBtn.querySelector('.btn-loading').style.display = 'none';
    }
}

/**
 * Delete category with confirmation
 * @param {number} categoryId
 */
async function deleteCategory(categoryId) {
    if (!confirm('Are you sure you want to delete this category? This may affect products.')) return;

    try {
        const response = await del(
            `${API_BASE_URL}${ENDPOINTS.CATEGORIES.DELETE(categoryId)}`
        );

        if (response.success) {
            showCategoryMessage('Category deleted successfully', 'success');
            loadCategories();
        } else {
            showCategoryMessage(response.message || 'Failed to delete category', 'error');
        }

    } catch (error) {
        console.error('Delete category error:', error);
        showCategoryMessage('Failed to delete category', 'error');
    }
}

// ────────────────────────────────────────────────
// Event Listeners & Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load initial data
    loadCategories();

    // Add category button
    addCategoryBtn.addEventListener('click', () => openCategoryModal());

    // Add first category button (empty state)
    if (addFirstCategoryBtn) {
        addFirstCategoryBtn.addEventListener('click', () => openCategoryModal());
    }

    // Save category form
    categoryForm.addEventListener('submit', saveCategory);

    // Cancel/close modal
    cancelCategoryBtn.addEventListener('click', closeCategoryModal);
    closeModalBtn.addEventListener('click', closeCategoryModal);

    // Close modal on outside click
    categoryModal.addEventListener('click', (e) => {
        if (e.target === categoryModal) {
            closeCategoryModal();
        }
    });

    // Table action delegation
    categoriesTableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        const categoryId = parseInt(target.dataset.id);

        if (target.classList.contains('btn-edit')) {
            const category = currentCategories.find(c => c.id === categoryId);
            if (category) openCategoryModal(category);
        } else if (target.classList.contains('btn-delete')) {
            await deleteCategory(categoryId);
        }
    });

    // Search functionality (live filter)
    document.getElementById('searchInput')?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase().trim();
        const filtered = currentCategories.filter(c =>
            c.name.toLowerCase().includes(term) ||
            (c.description && c.description.toLowerCase().includes(term))
        );
        renderCategories(filtered);
    });
});