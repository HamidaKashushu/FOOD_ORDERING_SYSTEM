// backend/js/foods.js
// Food-related API operations for Food Ordering System frontend

import { API_BASE } from './config.js';
import { showMessage } from './utils.js';

/**
 * Fetch all available foods with optional search and category filter
 * @param {string} [search=""] - Search term for food name
 * @param {number|null} [categoryId=null] - Category ID filter
 * @returns {Promise<object>} - { success, message, data: foods[] }
 */
async function getAllFoods(search = "", categoryId = null) {
    try {
        let url = `${API_BASE}/index.php?route=foods&action=list`;
        if (search.trim()) {
            url += `&search=${encodeURIComponent(search.trim())}`;
        }
        if (categoryId !== null && Number.isInteger(Number(categoryId)) && Number(categoryId) > 0) {
            url += `&category=${categoryId}`;
        }

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to load foods', 'error');
            throw new Error(data.message || 'Failed to fetch foods');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error while fetching foods', 'error');
        throw error;
    }
}

/**
 * Fetch single food by ID
 * @param {number} foodId
 * @returns {Promise<object>} - { success, message, data: food }
 */
async function getFoodById(foodId) {
    if (!Number.isInteger(foodId) || foodId <= 0) {
        showMessage('Invalid food ID', 'error');
        throw new Error('Invalid food ID');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=foods&action=single&id=${foodId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Food not found', 'error');
            throw new Error(data.message || 'Failed to fetch food');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error while fetching food details', 'error');
        throw error;
    }
}

/**
 * Create a new food item (admin)
 * @param {number} categoryId
 * @param {string} foodName
 * @param {string} description
 * @param {number} price
 * @param {string} imageUrl
 * @param {boolean} [isAvailable=true]
 * @returns {Promise<object>}
 */
async function createFood(categoryId, foodName, description, price, imageUrl, isAvailable = true) {
    if (!Number.isInteger(categoryId) || categoryId <= 0) {
        throw new Error('Invalid category ID');
    }
    if (!foodName.trim() || isNaN(price) || price < 0) {
        throw new Error('Invalid food details');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=foods&action=create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                category_id: categoryId,
                food_name: foodName.trim(),
                description: description.trim(),
                price: Number(price),
                image_url: imageUrl.trim(),
                is_available: !!isAvailable
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to create food', 'error');
            throw new Error(data.message || 'Create failed');
        }

        showMessage('Food item created successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error during food creation', 'error');
        throw error;
    }
}

/**
 * Update existing food item (admin)
 * @param {number} foodId
 * @param {number} [categoryId]
 * @param {string} [foodName]
 * @param {string} [description]
 * @param {number} [price]
 * @param {string} [imageUrl]
 * @param {boolean} [isAvailable]
 * @returns {Promise<object>}
 */
async function updateFood(foodId, categoryId, foodName, description, price, imageUrl, isAvailable) {
    if (!Number.isInteger(foodId) || foodId <= 0) {
        throw new Error('Invalid food ID');
    }

    const payload = {};
    if (categoryId !== undefined && Number.isInteger(categoryId) && categoryId > 0) {
        payload.category_id = categoryId;
    }
    if (foodName !== undefined && foodName.trim()) {
        payload.food_name = foodName.trim();
    }
    if (description !== undefined) {
        payload.description = description.trim();
    }
    if (price !== undefined && !isNaN(price) && price >= 0) {
        payload.price = Number(price);
    }
    if (imageUrl !== undefined) {
        payload.image_url = imageUrl.trim();
    }
    if (isAvailable !== undefined) {
        payload.is_available = !!isAvailable;
    }

    if (Object.keys(payload).length === 0) {
        throw new Error('No fields to update');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=foods&action=update&id=${foodId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to update food', 'error');
            throw new Error(data.message || 'Update failed');
        }

        showMessage('Food updated successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error during food update', 'error');
        throw error;
    }
}

/**
 * Delete a food item (admin)
 * @param {number} foodId
 * @returns {Promise<object>}
 */
async function deleteFood(foodId) {
    if (!Number.isInteger(foodId) || foodId <= 0) {
        throw new Error('Invalid food ID');
    }

    try {
        const response = await fetch(`${API_BASE}/index.php?route=foods&action=delete&id=${foodId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to delete food', 'error');
            throw new Error(data.message || 'Delete failed');
        }

        showMessage('Food deleted successfully', 'success');
        return data;
    } catch (error) {
        showMessage(error.message || 'Network error during food deletion', 'error');
        throw error;
    }
}

/**
 * Fetch all food categories
 * @returns {Promise<object>} - { success, message, data: categories[] }
 */
async function getCategories() {
    try {
        const response = await fetch(`${API_BASE}/index.php?route=foods&action=categories`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            showMessage(data.message || 'Failed to load categories', 'error');
            throw new Error(data.message || 'Failed to fetch categories');
        }

        return data;
    } catch (error) {
        showMessage(error.message || 'Network error while fetching categories', 'error');
        throw error;
    }
}

// Export functions (use with <script type="module">)
export {
    getAllFoods,
    getFoodById,
    createFood,
    updateFood,
    deleteFood,
    getCategories
};

// Optional global fallback for non-module scripts
window.FoodsAPI = {
    getAllFoods,
    getFoodById,
    createFood,
    updateFood,
    deleteFood,
    getCategories
};