/**
 * Food Ordering System - Admin Reports Script
 * Manages fetching, filtering, and visualizing system reports including:
 * - Sales & revenue metrics
 * - Orders & revenue trend charts
 * - Top selling products
 * - User activity summary
 *
 * Features:
 * - Date range filtering with validation
 * - Dynamic Chart.js visualizations
 * - Real-time updates on filter apply
 * - Loading states and error handling
 * - Responsive design integration
 *
 * Dependencies:
 * - Chart.js (via CDN)
 * - js/utils/fetch.js
 * - js/config/api.js
 *
 * @package FoodOrderingSystem
 * @version 1.0.0
 */

import { get } from '../utils/fetch.js';
import { API_BASE_URL, ENDPOINTS } from '../config/api.js';

// ────────────────────────────────────────────────
// DOM Elements
// ────────────────────────────────────────────────
const startDateInput      = document.getElementById('startDate');
const endDateInput        = document.getElementById('endDate');
const applyFilterBtn      = document.getElementById('applyFilterBtn');
const resetFilterBtn      = document.getElementById('resetFilterBtn');

const totalOrdersEl       = document.getElementById('totalOrders');
const totalRevenueEl      = document.getElementById('totalRevenue');
const totalUsersEl        = document.getElementById('totalUsers');
const topProductEl        = document.getElementById('topProduct');

const ordersTrendChartEl  = document.getElementById('ordersTrendChart');
const revenueTrendChartEl = document.getElementById('revenueTrendChart');
const topProductsTable    = document.getElementById('topProductsTable');

// Chart instances
let ordersTrendChart;
let revenueTrendChart;

// ────────────────────────────────────────────────
// Utility Functions
// ────────────────────────────────────────────────

/**
 * Format currency (TZS)
 * @param {number} amount
 * @returns {string}
 */
function formatCurrency(amount) {
    return `TZS ${parseFloat(amount || 0).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    })}`;
}

/**
 * Validate date range
 * @param {string} start
 * @param {string} end
 * @returns {boolean}
 */
function isValidDateRange(start, end) {
    if (!start || !end) return false;
    const startDate = new Date(start);
    const endDate = new Date(end);
    return startDate <= endDate && !isNaN(startDate) && !isNaN(endDate);
}

/**
 * Format date for API (YYYY-MM-DD)
 * @param {string} dateStr
 * @returns {string}
 */
function formatApiDate(dateStr) {
    return new Date(dateStr).toISOString().split('T')[0];
}

/**
 * Show loading state for elements
 * @param {HTMLElement} el
 * @param {boolean} show
 */
function toggleLoading(el, show) {
    if (el) {
        el.innerHTML = show 
            ? '<span class="loading-spinner"></span> Loading...'
            : '';
    }
}

/**
 * Show error message
 * @param {string} message
 */
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'dashboard-error';
    errorDiv.textContent = message;
    document.querySelector('.content-section').prepend(errorDiv);

    setTimeout(() => errorDiv.remove(), 8000);
}

// ────────────────────────────────────────────────
// Data Fetching & Rendering
// ────────────────────────────────────────────────

/**
 * Fetch and render all report data
 * @param {string} [startDate]
 * @param {string} [endDate]
 */
async function loadReports(startDate = null, endDate = null) {
    try {
        // Reset charts if they exist
        if (ordersTrendChart) ordersTrendChart.destroy();
        if (revenueTrendChart) revenueTrendChart.destroy();

        // Prepare query params
        const params = new URLSearchParams();
        if (startDate) params.append('start', startDate);
        if (endDate) params.append('end', endDate);

        const queryString = params.toString() ? `?${params.toString()}` : '';

        // Fetch all report endpoints
        const [
            salesData,
            ordersTrend,
            revenueTrend,
            topProducts
        ] = await Promise.all([
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.SALES}${queryString}`),
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.ORDERS}${queryString}`),
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.REVENUE_SUMMARY}${queryString}`),
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.POPULAR_PRODUCTS}${queryString}`)
        ]);

        // Update summary cards
        totalOrdersEl.textContent   = salesData?.total_orders || 0;
        totalRevenueEl.textContent  = formatCurrency(salesData?.total_revenue || 0);
        totalUsersEl.textContent    = salesData?.total_users || 0;
        topProductEl.textContent    = topProducts?.[0]?.name || '—';

        // Render charts
        renderOrdersTrendChart(ordersTrend);
        renderRevenueTrendChart(revenueTrend);
        renderTopProductsTable(topProducts);

    } catch (error) {
        console.error('Failed to load reports:', error);
        showError('Failed to load report data. Please try again.');
    }
}

/**
 * Render orders trend chart (line)
 * @param {Object} data - {labels: [], values: []}
 */
function renderOrdersTrendChart(data) {
    if (!data?.labels?.length) return;

    const ctx = ordersTrendChartEl.getContext('2d');
    ordersTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Orders',
                data: data.values,
                borderColor: '#4f46e5',
                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => `${context.parsed.y} orders`
                    }
                }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

/**
 * Render revenue trend chart (bar)
 * @param {Object} data - {labels: [], values: []}
 */
function renderRevenueTrendChart(data) {
    if (!data?.labels?.length) return;

    const ctx = revenueTrendChartEl.getContext('2d');
    revenueTrendChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Revenue (TZS)',
                data: data.values,
                backgroundColor: '#10b981',
                borderColor: '#059669',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: context => formatCurrency(context.parsed.y)
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => formatCurrency(value)
                    }
                }
            }
        }
    });
}

/**
 * Render top products table
 * @param {Array} products
 */
function renderTopProductsTable(products) {
    const tbody = topProductsTable.querySelector('tbody');
    tbody.innerHTML = '';

    if (!products?.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="text-center">No sales data available</td>
            </tr>
        `;
        return;
    }

    products.forEach((product, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${index + 1}</td>
            <td>${product.name}</td>
            <td>${product.total_quantity || 0}</td>
            <td>${formatCurrency(product.total_revenue)}</td>
        `;
        tbody.appendChild(row);
    });
}

// ────────────────────────────────────────────────
// Event Listeners & Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Set default date range (last 30 days)
    const today = new Date();
    const defaultEnd = today.toISOString().split('T')[0];
    const defaultStart = new Date(today);
    defaultStart.setDate(today.getDate() - 30);
    startDateInput.value = defaultStart.toISOString().split('T')[0];
    endDateInput.value = defaultEnd;

    // Initial load
    loadReports(startDateInput.value, endDateInput.value);

    // Apply filter
    applyFilterBtn.addEventListener('click', () => {
        if (!isValidDateRange(startDateInput.value, endDateInput.value)) {
            showError('Invalid date range. Start date must be before or equal to end date.');
            return;
        }

        loadReports(startDateInput.value, endDateInput.value);
    });

    // Reset filter
    resetFilterBtn.addEventListener('click', () => {
        const today = new Date().toISOString().split('T')[0];
        startDateInput.value = '';
        endDateInput.value = today;
        loadReports();
    });

    // Optional: Auto-refresh every 10 minutes
    setInterval(() => {
        loadReports(startDateInput.value, endDateInput.value);
    }, 10 * 60 * 1000);
});