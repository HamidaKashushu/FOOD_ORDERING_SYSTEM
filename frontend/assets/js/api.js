// frontend/assets/js/api.js

const API_BASE_URL = 'http://localhost/d_food/backend/api';
const UPLOADS_BASE_URL = 'http://localhost/d_food/backend'; // For images

class API {
    static async request(endpoint, method = 'GET', data = null, isFile = false) {
        const headers = {};
        
        // If not file upload, set JSON header
        if (!isFile && data) {
            headers['Content-Type'] = 'application/json';
        }

        const config = {
            method,
            headers,
        };

        if (data) {
            config.body = isFile ? data : JSON.stringify(data);
        }

        try {
            const response = await fetch(`${API_BASE_URL}/${endpoint}`, config);
            const text = await response.text(); // Get text first to debug if JSON fails
            try {
                const json = JSON.parse(text);
                if (!response.ok) {
                    throw new Error(json.message || 'API Error');
                }
                return json;
            } catch (e) {
                // If parsing fails, it might be a PHP error output
                if (!response.ok) throw new Error(text || 'Network Error');
                throw e; 
            }
        } catch (error) {
            console.error("API Request Failed:", error);
            throw error;
        }
    }

    static get(endpoint) { return this.request(endpoint, 'GET'); }
    static post(endpoint, data) { return this.request(endpoint, 'POST', data); }
    static upload(endpoint, formData) { return this.request(endpoint, 'POST', formData, true); }
}

const AuthService = {
    login: (email, password) => API.post('auth/login.php', { email, password }),
    register: (userData) => API.post('auth/register.php', userData),
    saveUser: (user) => {
        localStorage.setItem('dfood_user', JSON.stringify(user));
        // Reset/Update UI
        updateAuthUI();
    },
    getUser: () => {
        const u = localStorage.getItem('dfood_user');
        return u ? JSON.parse(u) : null;
    },
    logout: () => {
        localStorage.removeItem('dfood_user');
        localStorage.removeItem('dfood_cart');
        window.location.hash = '#/login';
        updateAuthUI();
    },
    isAdmin: () => {
        const user = AuthService.getUser();
        return user && user.role === 'admin';
    }
};

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerText = message;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function updatedCartCount() {
    const cart = JSON.parse(localStorage.getItem('dfood_cart') || '[]');
    const count = cart.reduce((a, b) => a + b.quantity, 0);
    const el = document.getElementById('cart-count');
    if (el) el.innerText = count;
}

function updateAuthUI() {
    const user = AuthService.getUser();
    const authLinks = document.getElementById('auth-links');
    const userLinks = document.getElementById('user-links');
    const adminLinks = document.getElementById('admin-links');

    if (user) {
        if(authLinks) authLinks.style.display = 'none';
        if(userLinks) userLinks.style.display = 'flex';
        
        if (user.role === 'admin' && adminLinks) {
            adminLinks.style.display = 'block';
        } else if (adminLinks) {
            adminLinks.style.display = 'none';
        }
    } else {
        if(authLinks) authLinks.style.display = 'flex';
        if(userLinks) userLinks.style.display = 'none';
        if(adminLinks) adminLinks.style.display = 'none';
    }
    updatedCartCount();
}
