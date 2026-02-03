// backend/js/admin.js
// Admin panel API operations for Food Ordering System frontend

import { API_BASE } from './config.js';
import { showMessage } from './utils.js';
import { getCurrentUser } from './auth.js';

/**
 * Fetch admin dashboard statistics
 * @param {number} adminId - Admin user ID
 * @returns {Promise<object>} Dashboard stats
 */
async function getDashboardStats(adminId) {
    if (!Number.isInteger(adminId) || adminId <= 0) {
        showMessage('Invalid admin ID', 'error');
        throw new Error('Invalid admin ID');
    }

    try {
        const response = await fetch(
            `${API_BASE}/index.php?route=admin&action=dashboard&admin_id=${adminId}`,
            {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to load dashboard stats', 'error');
            throw new Error(data.message || 'Failed to fetch dashboard');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error loading dashboard', 'error');
        throw error;
    }
}

/**
 * Fetch list of all users (admin view)
 * @param {number} adminId
 * @returns {Promise<object>}
 */
async function getAllUsers(adminId) {
    if (!Number.isInteger(adminId) || adminId <= 0) {
        throw new Error('Invalid admin ID');
    }

    try {
        const response = await fetch(
            `${API_BASE}/index.php?route=admin&action=users&admin_id=${adminId}`,
            {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to load users', 'error');
            throw new Error(data.message || 'Failed to fetch users');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error fetching users', 'error');
        throw error;
    }
}

/**
 * Delete a user (admin only)
 * @param {number} adminId
 * @param {number} userId
 * @returns {Promise<object>}
 */
async function deleteUser(adminId, userId) {
    if (!Number.isInteger(adminId) || adminId <= 0 || !Number.isInteger(userId) || userId <= 0) {
        showMessage('Invalid IDs provided', 'error');
        throw new Error('Invalid IDs');
    }

    try {
        const response = await fetch(
            `${API_BASE}/index.php?route=admin&action=delete_user&id=${userId}&admin_id=${adminId}`,
            {
                method: 'DELETE',
                headers: { 'Accept': 'application/json' }
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to delete user', 'error');
            throw new Error(data.message || 'Delete failed');
        }

        showMessage('User deleted successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error deleting user', 'error');
        throw error;
    }
}

/**
 * Create a new food category (admin)
 * @param {number} adminId
 * @param {string} categoryName
 * @param {string} [description=""]
 * @returns {Promise<object>}
 */
async function createCategory(adminId, categoryName, description = '') {
    if (!Number.isInteger(adminId) || adminId <= 0 || !categoryName.trim()) {
        showMessage('Invalid category details', 'error');
        throw new Error('Invalid category details');
    }

    try {
        const response = await fetch(
            `${API_BASE}/index.php?route=admin&action=create_category&admin_id=${adminId}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    category_name: categoryName.trim(),
                    description: description.trim()
                })
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to create category', 'error');
            throw new Error(data.message || 'Create failed');
        }

        showMessage('Category created successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error creating category', 'error');
        throw error;
    }
}

/**
 * Update existing category (admin)
 * @param {number} adminId
 * @param {number} categoryId
 * @param {string} [categoryName]
 * @param {string} [description]
 * @returns {Promise<object>}
 */
async function updateCategory(adminId, categoryId, categoryName, description) {
    if (!Number.isInteger(adminId) || adminId <= 0 || !Number.isInteger(categoryId) || categoryId <= 0) {
        throw new Error('Invalid IDs');
    }

    const payload = {};
    if (categoryName !== undefined && categoryName.trim()) {
        payload.category_name = categoryName.trim();
    }
    if (description !== undefined) {
        payload.description = description.trim();
    }

    if (Object.keys(payload).length === 0) {
        throw new Error('No fields to update');
    }

    try {
        const response = await fetch(
            `${API_BASE}/index.php?route=admin&action=update_category&id=${categoryId}&admin_id=${adminId}`,
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to update category', 'error');
            throw new Error(data.message || 'Update failed');
        }

        showMessage('Category updated successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error updating category', 'error');
        throw error;
    }
}

/**
 * Delete a category (admin)
 * @param {number} adminId
 * @param {number} categoryId
 * @returns {Promise<object>}
 */
async function deleteCategory(adminId, categoryId) {
    if (!Number.isInteger(adminId) || adminId <= 0 || !Number.isInteger(categoryId) || categoryId <= 0) {
        showMessage('Invalid IDs', 'error');
        throw new Error('Invalid IDs');
    }

    try {
        const response = await fetch(
            `${API_BASE}/index.php?route=admin&action=delete_category&id=${categoryId}&admin_id=${adminId}`,
            {
                method: 'DELETE',
                headers: { 'Accept': 'application/json' }
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to delete category', 'error');
            throw new Error(data.message || 'Delete failed');
        }

        showMessage('Category deleted successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error deleting category', 'error');
        throw error;
    }
}

/**
 * Fetch all orders (admin view)
 * @param {number} adminId
 * @returns {Promise<object>}
 */
async function getAllOrders(adminId) {
    if (!Number.isInteger(adminId) || adminId <= 0) {
        showMessage('Invalid admin ID', 'error');
        throw new Error('Invalid admin ID');
    }

    try {
        const response = await fetch(
            `${API_BASE}/index.php?route=admin&action=all_orders&admin_id=${adminId}`,
            {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to load all orders', 'error');
            throw new Error(data.message || 'Failed to fetch orders');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error loading orders', 'error');
        throw error;
    }
}

// Convenience: Use current logged-in admin
async function getCurrentAdminDashboard() {
    const user = getCurrentUser();
    if (!user || user.role_name?.toLowerCase() !== 'admin') {
        throw new Error('Admin access required');
    }
    return getDashboardStats(user.id);
}

async function getCurrentAdminUsers() {
    const user = getCurrentUser();
    if (!user || user.role_name?.toLowerCase() !== 'admin') {
        throw new Error('Admin access required');
    }
    return getAllUsers(user.id);
}

async function getCurrentAdminOrders() {
    const user = getCurrentUser();
    if (!user || user.role_name?.toLowerCase() !== 'admin') {
        throw new Error('Admin access required');
    }
    return getAllOrders(user.id);
}

// Export functions (use with <script type="module">)
export {
    getDashboardStats,
    getAllUsers,
    deleteUser,
    createCategory,
    updateCategory,
    deleteCategory,
    getAllOrders,
    getCurrentAdminDashboard,
    getCurrentAdminUsers,
    getCurrentAdminOrders
};

// Optional global fallback
window.AdminAPI = {
    getDashboardStats,
    getAllUsers,
    deleteUser,
    createCategory,
    updateCategory,
    deleteCategory,
    getAllOrders,
    getCurrentAdminDashboard,
    getCurrentAdminUsers,
    getCurrentAdminOrders
};