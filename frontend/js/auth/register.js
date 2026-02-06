/**
 * Food Ordering System - Registration Page Script
 * Handles client-side validation, registration API call,
 * success/error feedback, and post-registration redirect.
 *
 * Dependencies:
 * - js/utils/fetch.js
 * - js/utils/validator.js
 * - js/config/api.js
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { post } from '../utils/fetch.js';
import {
    isRequired,
    isEmail,
    minLength,
    matches
} from '../utils/validator.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const registerForm       = document.getElementById('registerForm');
const fullNameInput      = document.getElementById('fullName');
const emailInput         = document.getElementById('email');
const passwordInput      = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirmPassword');
const submitBtn          = document.getElementById('registerBtn');
const messageDisplay     = document.getElementById('formMessage');

// ────────────────────────────────────────────────
// Form Validation
// ────────────────────────────────────────────────

/**
 * Validate registration form inputs
 * @returns {{valid: boolean, message: string}}
 */
function validateRegisterForm() {
    // Full Name
    const nameCheck = isRequired(fullNameInput.value.trim(), 'Full name is required');
    if (!nameCheck.valid) {
        return { valid: false, message: nameCheck.message };
    }

    // Email
    const emailRequired = isRequired(emailInput.value.trim(), 'Email is required');
    if (!emailRequired.valid) {
        return { valid: false, message: emailRequired.message };
    }

    const emailFormat = isEmail(emailInput.value.trim());
    if (!emailFormat.valid) {
        return { valid: false, message: emailFormat.message };
    }

    // Password
    const passwordRequired = isRequired(passwordInput.value, 'Password is required');
    if (!passwordRequired.valid) {
        return { valid: false, message: passwordRequired.message };
    }

    const passwordLength = minLength(passwordInput.value, 8, 'Password must be at least 8 characters');
    if (!passwordLength.valid) {
        return { valid: false, message: passwordLength.message };
    }

    // Confirm Password
    const confirmCheck = matches(
        passwordInput.value,
        confirmPasswordInput.value,
        'Passwords do not match'
    );
    if (!confirmCheck.valid) {
        return { valid: false, message: confirmCheck.message };
    }

    return { valid: true, message: '' };
}

/**
 * Handle registration form submission
 * @param {Event} event - Form submit event
 */
async function handleRegister(event) {
    event.preventDefault();

    // Reset message
    messageDisplay.textContent = '';
    messageDisplay.className = 'form-message';

    // Show loading state
    submitBtn.disabled = true;
    submitBtn.querySelector('.btn-text').style.display = 'none';
    submitBtn.querySelector('.btn-loading').style.display = 'inline';

    const validation = validateRegisterForm();

    if (!validation.valid) {
        messageDisplay.textContent = validation.message;
        messageDisplay.className = 'form-message error';
        resetButtonState();
        return;
    }

    try {
        const response = await post(
            `${API_BASE_URL}${ENDPOINTS.AUTH.REGISTER}`,
            {
                full_name: fullNameInput.value.trim(),
                email: emailInput.value.trim(),
                password: passwordInput.value,
                phone: document.getElementById('phone')?.value.trim() || ''
            }
        );

        if (response.success) {
            messageDisplay.textContent = response.message || 'Registration successful! Redirecting to login...';
            messageDisplay.className = 'form-message success';

            // Redirect to login page after short delay
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 2000);
        } else {
            messageDisplay.textContent = response.message || 'Registration failed. Please try again.';
            messageDisplay.className = 'form-message error';
            resetButtonState();
        }

    } catch (error) {
        console.error('Registration error:', error);
        messageDisplay.textContent = error.message || 'Failed to connect to server. Please try again later.';
        messageDisplay.className = 'form-message error';
        resetButtonState();
    }
}

/**
 * Reset submit button to normal state
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
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }

    // Optional: Live validation on blur (enhances UX)
    if (emailInput) {
        emailInput.addEventListener('blur', () => {
            const result = isEmail(emailInput.value.trim());
            if (!result.valid && emailInput.value.trim() !== '') {
                emailInput.classList.add('input-error');
            } else {
                emailInput.classList.remove('input-error');
            }
        });
    }

    // Allow Enter key to submit from any field
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                registerForm.dispatchEvent(new Event('submit'));
            }
        });
    }
});

// ────────────────────────────────────────────────
// Exports (for testing or module usage)
// ────────────────────────────────────────────────
export {
    validateRegisterForm,
    handleRegister
};