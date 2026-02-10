/**
 * Food Ordering System - Login Page Script
 * Handles form validation, API login request, token storage,
 * and redirect based on user role.
 *
 * Dependencies:
 * - js/utils/fetch.js
 * - js/utils/storage.js
 * - js/utils/validator.js
 * - js/config/api.js
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { post } from '../utils/fetch.js';
import { setSession } from '../utils/auth.js';
import { isRequired, isEmail, minLength } from '../utils/validator.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const loginForm = document.getElementById('loginForm');
const emailInput = document.getElementById('email');
const passwordInput = document.getElementById('password');
const submitBtn = document.getElementById('loginBtn');
const messageDisplay = document.getElementById('errorMessage');

// ────────────────────────────────────────────────
// Form Validation & Submission
// ────────────────────────────────────────────────

/**
 * Validate login form inputs
 * @returns {{valid: boolean, message: string}}
 */
function validateLoginForm() {
    // Email validation
    const emailResult = isRequired(emailInput.value.trim(), 'Email is required');
    if (!emailResult.valid) {
        return { valid: false, message: emailResult.message };
    }

    const emailFormat = isEmail(emailInput.value.trim());
    if (!emailFormat.valid) {
        return { valid: false, message: emailFormat.message };
    }

    // Password validation
    const passwordResult = isRequired(passwordInput.value, 'Password is required');
    if (!passwordResult.valid) {
        return { valid: false, message: passwordResult.message };
    }

    const minLengthCheck = minLength(passwordInput.value, 6, 'Password must be at least 6 characters');
    if (!minLengthCheck.valid) {
        return { valid: false, message: minLengthCheck.message };
    }

    return { valid: true, message: '' };
}

/**
 * Handle form submission
 * @param {Event} event - Form submit event
 */
async function handleLogin(event) {
    event.preventDefault();

    // Reset message
    messageDisplay.textContent = '';
    messageDisplay.className = 'error-message';

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.querySelector('.btn-text').style.display = 'none';
    submitBtn.querySelector('.btn-loading').style.display = 'inline';

    const validation = validateLoginForm();

    if (!validation.valid) {
        messageDisplay.textContent = validation.message;
        resetButtonState();
        return;
    }

    try {
        const response = await post(
            `${API_BASE_URL}${ENDPOINTS.AUTH.LOGIN}`,
            {
                email: emailInput.value.trim(),
                password: passwordInput.value
            }
        );

        if (response.success) {
            // Store token and user data
            setSession(response.token, response.user);

            // Redirect based on role
            const user = response.user;
            const redirectUrl = user.role === 'admin'
                ? '../admin/dashboard.html'
                : '../index.html';

            window.location.href = redirectUrl;
        } else {
            messageDisplay.textContent = response.message || 'Invalid email or password';
            resetButtonState();
        }

    } catch (error) {
        console.error('Login error:', error);
        messageDisplay.textContent = error.message || 'Failed to connect to server. Please try again.';
        resetButtonState();
    }
}

/**
 * Reset button to normal state
 */
function resetButtonState() {
    submitBtn.disabled = false;
    submitBtn.querySelector('.btn-text').style.display = 'inline';
    submitBtn.querySelector('.btn-loading').style.display = 'none';
}

// ────────────────────────────────────────────────
// Event Listeners
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    // Optional: Allow Enter key on password field to submit
    if (passwordInput) {
        passwordInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                loginForm.dispatchEvent(new Event('submit'));
            }
        });
    }
});

// ────────────────────────────────────────────────
// Exports (for testing or module usage)
// ────────────────────────────────────────────────
export {
    validateLoginForm,
    handleLogin
};