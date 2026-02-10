/**
 * Food Ordering System - Admin Dashboard Script
 * Manages fetching and displaying key metrics, charts, and recent orders
 * for the admin dashboard interface.
 *
 * Features:
 * - Real-time metrics loading (users, orders, revenue, products)
 * - Interactive charts using Chart.js
 * - Recent orders table with status indicators
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
const totalUsersEl = document.getElementById('totalUsers');
const totalOrdersEl = document.getElementById('totalOrders');
const totalRevenueEl = document.getElementById('totalRevenue');
const totalProductsEl = document.getElementById('totalProducts');

const ordersChartEl = document.getElementById('ordersChart');
const revenueChartEl = document.getElementById('revenueChart');

const recentOrdersBody = document.getElementById('recentOrdersTable');

// Chart instances
let ordersChart;
let revenueChart;

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
 * Format date to Tanzanian readable format
 * @param {string} dateStr
 * @returns {string}
 */
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
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

// ────────────────────────────────────────────────
// Data Fetching
// ────────────────────────────────────────────────

/**
 * Fetch dashboard metrics
 */
async function loadDashboardMetrics() {
    try {
        // For simplicity, assuming separate endpoints or a dashboard endpoint
        // You can adjust to your actual API structure
        const [users, orders, revenue, products] = await Promise.all([
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.USERS_COUNT}`),
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.ORDERS_COUNT}`),
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.REVENUE_TOTAL}`),
            get(`${API_BASE_URL}${ENDPOINTS.REPORTS.PRODUCTS_COUNT}`)
        ]);

        totalUsersEl.textContent = users?.count || 0;
        totalOrdersEl.textContent = orders?.count || 0;
        totalRevenueEl.textContent = formatCurrency(revenue?.total || 0);
        totalProductsEl.textContent = products?.count || 0;

    } catch (error) {
        console.error('Failed to load dashboard metrics:', error);
        showError('Failed to load dashboard data');
    }
}

/**
 * Fetch and render orders trend chart
 */
async function loadOrdersTrendChart() {
    try {
        const data = await get(`${API_BASE_URL}${ENDPOINTS.REPORTS.ORDERS_TREND}`);

        if (!data || !data.labels || !data.values) return;

        if (ordersChart) {
            ordersChart.destroy();
        }

        const ctx = ordersChartEl.getContext('2d');
        ordersChart = new Chart(ctx, {
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
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });

    } catch (error) {
        console.error('Failed to load orders trend chart:', error);
    }
}

/**
 * Fetch and render revenue trend chart
 */
async function loadRevenueTrendChart() {
    try {
        const data = await get(`${API_BASE_URL}${ENDPOINTS.REPORTS.REVENUE_TREND}`);

        if (!data || !data.labels || !data.values) return;

        if (revenueChart) {
            revenueChart.destroy();
        }

        const ctx = revenueChartEl.getContext('2d');
        revenueChart = new Chart(ctx, {
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

    } catch (error) {
        console.error('Failed to load revenue trend chart:', error);
    }
}

/**
 * Load recent orders table
 */
async function loadRecentOrders() {
    try {
        const orders = await get(`${API_BASE_URL}/api/orders?limit=5&sort=desc`);

        if (!orders || orders.length === 0) {
            recentOrdersBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">No recent orders</td>
                </tr>
            `;
            return;
        }

        recentOrdersBody.innerHTML = '';

        orders.forEach(order => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>#${order.id}</td>
                <td>${order.customer_name || 'Guest'}</td>
                <td>${formatCurrency(order.total_amount)}</td>
                <td>
                    <span class="status-badge status-${order.status}">
                        ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                    </span>
                </td>
                <td>${formatDate(order.created_at)}</td>
                <td>
                    <a href="orders.html#order-${order.id}" class="btn btn-sm btn-outline">
                        View
                    </a>
                </td>
            `;
            recentOrdersBody.appendChild(row);
        });

    } catch (error) {
        console.error('Failed to load recent orders:', error);
        recentOrdersBody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center error">Failed to load orders</td>
            </tr>
        `;
    }
}

/**
 * Show error message in dashboard
 * @param {string} message
 */
function showError(message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'dashboard-error';
    errorDiv.textContent = message;
    document.querySelector('.dashboard-content').prepend(errorDiv);

    setTimeout(() => errorDiv.remove(), 8000);
}

// ────────────────────────────────────────────────
// Initialization
// ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {
    // Load all dashboard data in parallel
    await Promise.all([
        loadDashboardMetrics(),
        loadOrdersTrendChart(),
        loadRevenueTrendChart(),
        loadRecentOrders()
    ]);

    // Optional: Refresh data every 5 minutes
    setInterval(() => {
        loadDashboardMetrics();
        loadRecentOrders();
    }, 5 * 60 * 1000);
});