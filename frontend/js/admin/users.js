/**
 * Food Ordering System - Admin Users Management Script
 * Handles CRUD operations and role/status management for users in the admin panel.
 *
 * Features:
 * - Fetch and display all users in responsive table
 * - Add new user with role assignment
 * - Edit user details (name, phone, status)
 * - Delete users with confirmation
 * - Change user role (admin/customer)
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
import { isRequired, isEmail, minLength } from '../utils/validator.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const usersTableBody = document.getElementById('usersBody');
const addUserBtn = document.getElementById('addUserBtn');
const userModal = document.getElementById('userModal');
const modalTitle = document.getElementById('modalTitle');
const userForm = document.getElementById('userForm');
const userIdInput = document.getElementById('userId');
const fullNameInput = document.getElementById('fullName');
const emailInput = document.getElementById('email');
const phoneInput = document.getElementById('phone');
const passwordGroup = document.getElementById('passwordGroup');
const passwordInput = document.getElementById('password');
const roleSelect = document.getElementById('role');
const statusSelect = document.getElementById('status');
const saveUserBtn = document.getElementById('saveUserBtn');
const cancelUserBtn = document.getElementById('cancelUserBtn');
const closeModalBtn = document.getElementById('closeModal');
const userMessage = document.getElementById('userMessage');
const emptyUsers = document.getElementById('emptyUsers');
const addFirstUserBtn = document.getElementById('addFirstUserBtn');

// ────────────────────────────────────────────────
// State & Constants
// ────────────────────────────────────────────────
let currentUsers = [];
let editingUserId = null;

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

/**
 * Show message in modal
 * @param {string} msg
 * @param {string} type - 'success' | 'error'
 */
function showUserMessage(msg, type = 'error') {
    userMessage.textContent = msg;
    userMessage.className = `message ${type}`;
    userMessage.style.display = msg ? 'block' : 'none';
}

/**
 * Reset form to add mode
 */
function resetUserForm() {
    editingUserId = null;
    modalTitle.textContent = 'Add New User';
    userForm.reset();
    userIdInput.value = '';
    passwordGroup.style.display = 'block';
    passwordInput.required = true;
    userMessage.style.display = 'none';
    saveUserBtn.disabled = false;
}

/**
 * Open modal for add/edit
 * @param {Object|null} user - User data for edit mode
 */
function openUserModal(user = null) {
    resetUserForm();

    if (user) {
        editingUserId = user.id;
        modalTitle.textContent = 'Edit User';
        fullNameInput.value = user.full_name || '';
        emailInput.value = user.email || '';
        phoneInput.value = user.phone || '';
        roleSelect.value = user.role || 'customer';
        statusSelect.value = user.status || 'active';

        // Hide password field when editing
        passwordGroup.style.display = 'none';
        passwordInput.required = false;
    }

    userModal.style.display = 'block';
}

/**
 * Close user modal
 */
function closeUserModal() {
    userModal.style.display = 'none';
    resetUserForm();
}

// ────────────────────────────────────────────────
// API Functions
// ────────────────────────────────────────────────

/**
 * Load all users and render table
 */
async function loadUsers() {
    try {
        const users = await get(`${API_BASE_URL}${ENDPOINTS.USERS.ALL_USERS}`);
        currentUsers = users || [];

        usersTableBody.innerHTML = '';

        if (currentUsers.length === 0) {
            emptyUsers.style.display = 'block';
            return;
        }

        emptyUsers.style.display = 'none';

        currentUsers.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${user.full_name || '—'}</td>
                <td>${user.email}</td>
                <td>${user.phone || '—'}</td>
                <td>
                    <span class="role-badge role-${user.role}">
                        ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}
                    </span>
                </td>
                <td>
                    <span class="status-badge status-${user.status}">
                        ${user.status === 'active' ? 'Active' : 'Blocked'}
                    </span>
                </td>
                <td class="actions">
                    <button class="btn btn-sm btn-edit" data-id="${user.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-delete" data-id="${user.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            usersTableBody.appendChild(row);
        });

    } catch (error) {
        console.error('Failed to load users:', error);
        usersTableBody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center error">
                    Failed to load users. Please refresh the page.
                </td>
            </tr>
        `;
    }
}

/**
 * Save user (add or edit)
 * @param {Event} event
 */
async function saveUser(event) {
    event.preventDefault();

    const formData = new FormData(userForm);
    const userData = Object.fromEntries(formData);

    // Client-side validation
    const validations = [
        { check: isRequired(userData.full_name), msg: 'Full name is required' },
        { check: isEmail(userData.email), msg: 'Valid email is required' }
    ];

    if (!editingUserId) {
        validations.push(
            { check: isRequired(userData.password), msg: 'Password is required for new users' },
            { check: minLength(userData.password, 8), msg: 'Password must be at least 8 characters' }
        );
    }

    for (const v of validations) {
        if (!v.check.valid) {
            showUserMessage(v.msg, 'error');
            return;
        }
    }

    try {
        saveUserBtn.disabled = true;
        saveUserBtn.querySelector('.btn-text').style.display = 'none';
        saveUserBtn.querySelector('.btn-loading').style.display = 'inline';

        let response;

        if (editingUserId) {
            // Update existing user (password optional)
            response = await put(
                `${API_BASE_URL}${ENDPOINTS.USERS.UPDATE_USER(editingUserId)}`,
                userData
            );
        } else {
            // Create new user
            response = await post(
                `${API_BASE_URL}${ENDPOINTS.USERS.ALL_USERS}`,
                userData
            );
        }

        if (response.success) {
            showUserMessage(
                editingUserId ? 'User updated successfully' : 'User created successfully',
                'success'
            );
            closeUserModal();
            loadUsers();
        } else {
            showUserMessage(response.message || 'Operation failed', 'error');
        }

    } catch (error) {
        console.error('User save error:', error);
        showUserMessage('Failed to save user. Please try again.', 'error');
    } finally {
        saveUserBtn.disabled = false;
        saveUserBtn.querySelector('.btn-text').style.display = 'inline';
        saveUserBtn.querySelector('.btn-loading').style.display = 'none';
    }
}

/**
 * Delete user with confirmation
 * @param {number} userId
 */
async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;

    try {
        const response = await del(
            `${API_BASE_URL}${ENDPOINTS.USERS.DELETE_USER(userId)}`
        );

        if (response.success) {
            showUserMessage('User deleted successfully', 'success');
            loadUsers();
        } else {
            showUserMessage(response.message || 'Failed to delete user', 'error');
        }

    } catch (error) {
        console.error('Delete user error:', error);
        showUserMessage('Failed to delete user', 'error');
    }
}

/**
 * Change user role
 * @param {number} userId
 * @param {string} newRole
 */
async function changeUserRole(userId, newRole) {
    try {
        const response = await post(
            `${API_BASE_URL}${ENDPOINTS.USERS.ASSIGN_ROLE(userId)}`,
            { role: newRole }
        );

        if (response.success) {
            showUserMessage(`Role changed to ${newRole}`, 'success');
            loadUsers();
        } else {
            showUserMessage(response.message || 'Failed to change role', 'error');
        }

    } catch (error) {
        console.error('Change role error:', error);
        showUserMessage('Failed to change role', 'error');
    }
}

// ────────────────────────────────────────────────
// Event Listeners & Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Load initial data
    loadUsers();

    // Add user button
    addUserBtn.addEventListener('click', () => openUserModal());

    // Add first user button (empty state)
    if (addFirstUserBtn) {
        addFirstUserBtn.addEventListener('click', () => openUserModal());
    }

    // Save user form
    userForm.addEventListener('submit', saveUser);

    // Cancel/close modal
    cancelUserBtn.addEventListener('click', closeUserModal);
    closeModalBtn.addEventListener('click', closeUserModal);

    // Close modal on outside click
    userModal.addEventListener('click', (e) => {
        if (e.target === userModal) {
            closeUserModal();
        }
    });

    // Table action delegation
    usersTableBody.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        const userId = parseInt(target.dataset.id);

        if (target.classList.contains('btn-edit')) {
            const user = currentUsers.find(u => u.id === userId);
            if (user) openUserModal(user);
        } else if (target.classList.contains('btn-delete')) {
            await deleteUser(userId);
        }
    });

    // Role change (if using dropdown in table - optional enhancement)
    // For now, role change is handled in edit modal
});