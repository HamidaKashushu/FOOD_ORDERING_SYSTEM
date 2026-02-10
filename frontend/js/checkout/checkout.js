/**
 * Food Ordering System - Checkout Script
 * Handles address selection/creation, payment method, and order placement.
 */

import { get, post } from '../utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';
import { showToast, formatCurrency } from '../main.js';
import { getToken } from '../auth/auth.js';

// DOM Elements
const savedAddressesContainer = document.getElementById('existingAddresses');
const newAddressForm = document.getElementById('newAddressForm');
const checkoutForm = document.getElementById('checkoutForm');
const paymentMethods = document.querySelectorAll('input[name="payment_method"]'); // Changed from paymentMethod to payment_method based on HTML
const orderSummaryContainer = document.getElementById('summaryItems');
const subtotalDisplay = document.getElementById('subtotal');
const deliveryFeeDisplay = document.getElementById('deliveryFee');
const totalDisplay = document.getElementById('grandTotal');
const placeOrderBtn = document.getElementById('placeOrderBtn');

// State
let addresses = [];
let selectedAddressId = null;
let selectedPaymentMethod = 'cash';
let cartTotal = 0;
const DELIVERY_FEE = 2000;

document.addEventListener('DOMContentLoaded', () => {
    if (!getToken()) {
        window.location.href = 'login.html';
        return;
    }

    loadCartSummary();
    loadAddresses();

    // Event Listeners
    if (newAddressForm) {
        newAddressForm.addEventListener('submit', handleNewAddress);
    }

    // Payment method change
    paymentMethods.forEach(input => {
        input.addEventListener('change', (e) => {
            selectedPaymentMethod = e.target.value;
        });
    });

    // Place Order
    if (placeOrderBtn) {
        placeOrderBtn.addEventListener('click', placeOrder);
    }
});

/**
 * Load Cart Summary
 */
async function loadCartSummary() {
    try {
        const cart = await get(`${API_BASE_URL}${ENDPOINTS.CART.GET_CART}`);
        if (!cart || !cart.items || cart.items.length === 0) {
            alert('Your cart is empty');
            window.location.href = 'menu.html';
            return;
        }

        renderOrderSummary(cart.items);
        updateTotals(cart.total);

    } catch (error) {
        console.error('Failed to load cart:', error);
    }
}

function renderOrderSummary(items) {
    if (!orderSummaryContainer) return;

    orderSummaryContainer.innerHTML = items.map(item => `
        <div class="summary-item">
            <span>${item.quantity}x ${item.name}</span>
            <span>${formatCurrency(item.subtotal)}</span>
        </div>
    `).join('');
}

function updateTotals(subtotal) {
    cartTotal = parseFloat(subtotal);
    if (subtotalDisplay) subtotalDisplay.textContent = formatCurrency(cartTotal);
    if (deliveryFeeDisplay) deliveryFeeDisplay.textContent = formatCurrency(DELIVERY_FEE);
    if (totalDisplay) totalDisplay.textContent = formatCurrency(cartTotal + DELIVERY_FEE);
}

/**
 * Load User Addresses
 */
async function loadAddresses() {
    try {
        addresses = await get(`${API_BASE_URL}${ENDPOINTS.ADDRESSES.GET_ALL}`);
        renderAddresses();
    } catch (error) {
        console.error('Failed to load addresses:', error);
    }
}

function renderAddresses() {
    if (!savedAddressesContainer) return;

    if (addresses.length === 0) {
        savedAddressesContainer.innerHTML = '<p>No saved addresses. Please add one.</p>';
        return;
    }

    savedAddressesContainer.innerHTML = addresses.map(addr => `
        <div class="address-card ${selectedAddressId == addr.id ? 'selected' : ''}" onclick="selectAddress(${addr.id})">
            <input type="radio" name="address" value="${addr.id}" ${selectedAddressId == addr.id ? 'checked' : ''}>
            <div class="address-details">
                <strong>${addr.type.toUpperCase()}</strong>
                <p>${addr.street}, ${addr.city}</p>
                <p>${addr.region || ''} ${addr.country}</p>
            </div>
        </div>
    `).join('');

    // Pre-select first if none selected
    if (!selectedAddressId && addresses.length > 0) {
        selectAddress(addresses[0].id);
    }
}

// Make globally available for onclick
window.selectAddress = function (id) {
    selectedAddressId = id;
    renderAddresses(); // Re-render to update styling
}

/**
 * Handle New Address Submission
 */
async function handleNewAddress(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    // Basic client validation
    if (!data.street || !data.city || !data.country) {
        showToast('Please fill all required address fields', 'error');
        return;
    }

    try {
        const response = await post(`${API_BASE_URL}${ENDPOINTS.ADDRESSES.CREATE}`, data);
        if (response.success) {
            showToast('Address added', 'success');
            e.target.reset();
            // Hide modal if using one
            const modal = document.getElementById('addressModal');
            if (modal) modal.style.display = 'none'; // logic dependent on UI implementation

            await loadAddresses();
            // Select the new address (last one)
            if (addresses.length > 0) selectAddress(addresses[0].id);
        } else {
            showToast(response.message || 'Failed to add address', 'error');
        }
    } catch (error) {
        console.error('Add address error:', error);
        showToast('Failed to add address', 'error');
    }
}

/**
 * Place Order
 */
async function placeOrder() {
    if (!selectedAddressId) {
        showToast('Please select a delivery address', 'error');
        return;
    }

    const confirmOrder = confirm(`Place order with total ${formatCurrency(cartTotal + DELIVERY_FEE)}?`);
    if (!confirmOrder) return;

    placeOrderBtn.disabled = true;
    placeOrderBtn.textContent = 'Processing...';

    try {
        const response = await post(`${API_BASE_URL}${ENDPOINTS.ORDERS.CREATE}`, {
            address_id: selectedAddressId,
            payment_method: selectedPaymentMethod
        });

        if (response.success) {
            showToast('Order placed successfully!', 'success');
            setTimeout(() => {
                window.location.href = 'orders.html'; // Redirect to orders page
            }, 2000);
        } else {
            showToast(response.message || 'Order failed', 'error');
            placeOrderBtn.disabled = false;
            placeOrderBtn.textContent = 'Place Order';
        }

    } catch (error) {
        console.error('Order placement error:', error);
        showToast('Order failed. Please try again.', 'error');
        placeOrderBtn.disabled = false;
        placeOrderBtn.textContent = 'Place Order';
    }
}
