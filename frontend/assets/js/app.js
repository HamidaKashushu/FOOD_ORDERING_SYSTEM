// frontend/assets/js/app.js

const app = document.getElementById('app');

// Router
async function router() {
    const hash = window.location.hash || '#/';

    // Auth Guard
    const user = AuthService.getUser();
    if (hash.startsWith('#/admin') && (!user || !AuthService.isAdmin())) {
        window.location.hash = '#/login';
        return;
    }
    if ((hash.startsWith('#/cart') || hash.startsWith('#/orders')) && !user) {
        showToast("Please login first", "warning");
        window.location.hash = '#/login';
        return;
    }

    // Routing Logic
    if (hash === '#/') {
        renderHome();
    } else if (hash === '#/menu') {
        renderMenu();
    } else if (hash === '#/login') {
        renderLogin();
    } else if (hash === '#/register') {
        renderRegister();
    } else if (hash === '#/admin') {
        renderAdminDashboard();
    } else if (hash === '#/admin/add-product') {
        renderAddProduct();
    } else if (hash === '#/cart') {
        renderCart();
    } else if (hash === '#/orders') {
        renderOrders();
    } else {
        app.innerHTML = '<h1>404 - Page Not Found</h1>';
    }

    updateAuthUI();
}

window.addEventListener('hashchange', router);
window.addEventListener('load', router);

// --- VIEW FUNCTIONS ---

// Home View
function renderHome() {
    app.innerHTML = `
        <section class="hero" style="text-align:center; padding: 4rem 0;">
            <h1 style="font-size: 3rem; color: var(--primary);">Taste the Simplicity</h1>
            <p style="font-size: 1.2rem;">Authentic flavors delivered to your doorstep.</p>
            <a href="#/menu" class="btn btn-primary">Order Now</a>
        </section>
        <div style="margin-top: 2rem;">
            <h2>Popular Categories</h2>
            <div id="categories-container" class="grid grid-3"></div>
        </div>
    `;
    loadCategoriesForHome();
}

async function loadCategoriesForHome() {
    try {
        const res = await API.get('categories/read.php');
        const container = document.getElementById('categories-container');
        if (res.records) {
            container.innerHTML = res.records.map(c => `
                <div class="card p-4" style="text-align:center; padding: 2rem;">
                    <h3>${c.name}</h3>
                    <p>${c.description || ''}</p>
                </div>
            `).join('');
        }
    } catch (e) { console.error(e); }
}

// Menu View
async function renderMenu() {
    app.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
            <h1>Our Menu</h1>
            <input type="text" id="search-input" class="form-control" style="width: 300px;" placeholder="Search food...">
        </div>
        <div id="menu-container" class="grid grid-4"></div>
    `;

    loadProducts();

    document.getElementById('search-input').addEventListener('input', (e) => {
        loadProducts(e.target.value);
    });
}

async function loadProducts(search = '') {
    const container = document.getElementById('menu-container');
    container.innerHTML = '<div class="loader">Loading menu...</div>';

    try {
        let endpoint = 'products/read.php';
        if (search) endpoint += `?s=${search}`;

        const res = await API.get(endpoint);

        if (res.records && res.records.length > 0) {
            container.innerHTML = res.records.map(p => `
                <div class="card">
                    <img src="${UPLOADS_BASE_URL}/${p.image_path}" class="card-img" alt="${p.name}" onerror="this.src='https://via.placeholder.com/300?text=No+Image'">
                    <div class="card-body">
                        <h5 class="card-title">${p.name}</h5>
                        <p class="card-text">${p.description ? p.description.substring(0, 50) + '...' : ''}</p>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="card-price">${parseFloat(p.price).toLocaleString()} TZS</span>
                            <button class="btn btn-primary sm" onclick="addToCart(${p.product_id}, '${p.name}', ${p.price})">Add</button>
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p>No products found.</p>';
        }
    } catch (e) {
        container.innerHTML = '<p>Error loading products.</p>';
    }
}

// Cart Logic
window.addToCart = function (id, name, price) {
    let cart = JSON.parse(localStorage.getItem('dfood_cart') || '[]');
    const existing = cart.find(item => item.product_id === id);
    if (existing) {
        existing.quantity++;
    } else {
        cart.push({ product_id: id, name, unit_price: price, quantity: 1 });
    }
    localStorage.setItem('dfood_cart', JSON.stringify(cart));
    showToast(`${name} added to cart`);
    updatedCartCount();
}

function renderCart() {
    const cart = JSON.parse(localStorage.getItem('dfood_cart') || '[]');

    if (cart.length === 0) {
        app.innerHTML = `<h1>Your Cart</h1><p>Your cart is empty. <a href="#/menu">Go to Menu</a></p>`;
        return;
    }

    const total = cart.reduce((acc, item) => acc + (item.unit_price * item.quantity), 0);

    app.innerHTML = `
        <h1>Your Cart</h1>
        <div class="card card-body">
            <table style="width:100%; text-align:left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--gray-200);">
                        <th style="padding:10px;">Item</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${cart.map((item, index) => `
                        <tr style="border-bottom: 1px solid var(--gray-200);">
                            <td style="padding:10px;">${item.name}</td>
                            <td>${parseFloat(item.unit_price).toLocaleString()} TZS</td>
                            <td>${item.quantity}</td>
                            <td>${(item.unit_price * item.quantity).toLocaleString()} TZS</td>
                            <td><button class="btn btn-danger sm" onclick="removeFromCart(${index})">Remove</button></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <div style="margin-top: 2rem; text-align:right;">
                <h3>Total: ${total.toLocaleString()} TZS</h3>
                <div class="form-group" style="text-align:left; max-width: 400px; margin-left: auto;">
                    <label>Delivery Address</label>
                    <textarea id="delivery-address" class="form-control" rows="3" placeholder="Enter your full address in Tanzania"></textarea>
                </div>
                <button class="btn btn-success" onclick="placeOrder()">Checkout</button>
            </div>
        </div>
    `;
}

window.removeFromCart = function (index) {
    let cart = JSON.parse(localStorage.getItem('dfood_cart') || '[]');
    cart.splice(index, 1);
    localStorage.setItem('dfood_cart', JSON.stringify(cart));
    renderCart();
    updatedCartCount();
}

window.placeOrder = async function () {
    const address = document.getElementById('delivery-address').value;
    if (!address) {
        showToast("Please enter delivery address", "danger");
        return;
    }

    const cart = JSON.parse(localStorage.getItem('dfood_cart') || '[]');
    const user = AuthService.getUser();

    const total = cart.reduce((acc, item) => acc + (item.unit_price * item.quantity), 0);

    const orderData = {
        user_id: user.user_id,
        items: cart,
        total_amount: total,
        delivery_address: address,
        payment_method: 'mobile_money' // Defaulting for demo
    };

    try {
        const res = await API.post('orders/create.php', orderData);
        showToast("Order Placed Successfully!", "success");
        localStorage.removeItem('dfood_cart');
        updatedCartCount();
        window.location.hash = '#/orders';
    } catch (e) {
        showToast(e.message, "danger");
    }
}

// Admin Logic for Adding Products
function renderAdminDashboard() {
    app.innerHTML = `
        <h1>Admin Dashboard</h1>
        <div class="grid grid-2">
            <div class="card card-body">
                <h3>Manage Products</h3>
                <a href="#/admin/add-product" class="btn btn-primary">Add New Product</a>
            </div>
            <div class="card card-body">
                <h3>Recent Orders</h3>
                <div id="admin-orders-list">Loading...</div>
            </div>
        </div>
    `;
    loadAdminOrders();
}

async function loadAdminOrders() {
    try {
        const res = await API.get('orders/read.php?role=admin');
        const container = document.getElementById('admin-orders-list');
        if (res.records) {
            container.innerHTML = `<ul>${res.records.slice(0, 5).map(o => `<li>Order #${o.order_id} - ${o.user_name} - ${parseFloat(o.total_amount).toLocaleString()} TZS <span class="badge" style="background:${o.status === 'pending' ? 'orange' : 'green'}">${o.status}</span></li>`).join('')}</ul>`;
        } else {
            container.innerHTML = "No orders yet.";
        }
    } catch (e) { console.error(e); }
}

function renderAddProduct() {
    app.innerHTML = `
        <h1>Add New Product</h1>
        <form id="add-product-form" class="card card-body" style="max-width: 600px;">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" id="category-select" class="form-control" required>
                    <option value="">Loading...</option>
                </select>
            </div>
            <div class="form-group">
                <label>Price (TZS)</label>
                <input type="number" name="price" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Image</label>
                <input type="file" name="image" class="form-control" accept="image/*" required>
            </div>
            <button type="submit" class="btn btn-primary">Upload Product</button>
        </form>
    `;

    API.get('categories/read.php').then(res => {
        const select = document.getElementById('category-select');
        if (res.records) {
            select.innerHTML = res.records.map(c => `<option value="${c.category_id}">${c.name}</option>`).join('');
        }
    });

    document.getElementById('add-product-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        try {
            await API.upload('products/create.php', formData);
            showToast("Product Added!", "success");
            window.location.hash = '#/admin';
        } catch (err) {
            showToast(err.message, "danger");
        }
    });
}

// Orders View
async function renderOrders() {
    const user = AuthService.getUser();
    app.innerHTML = `<h1>My Orders</h1><div id="orders-list">Loading...</div>`;

    try {
        const res = await API.get(`orders/read.php?user_id=${user.user_id}`);
        const container = document.getElementById('orders-list');
        if (res.records) {
            container.innerHTML = res.records.map(o => `
                <div class="card card-body" style="margin-bottom: 1rem;">
                    <h3>Order #${o.order_id} <small style="float:right;">${o.date}</small></h3>
                    <p>Status: <strong>${o.status}</strong></p>
                    <p>Total: <strong>${parseFloat(o.total_amount).toLocaleString()} TZS</strong></p>
                    <ul>
                        ${o.items.map(i => `<li>${i.name} x ${i.quantity}</li>`).join('')}
                    </ul>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p>No orders found.</p>';
        }
    } catch (e) {
        document.getElementById('orders-list').innerHTML = '<p>No orders yet.</p>';
    }
}

// Auth Views
function renderLogin() {
    app.innerHTML = `
        <div style="max-width: 400px; margin: 4rem auto;">
            <h1>Login</h1>
            <form id="login-form">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Login</button>
                <p style="margin-top:1rem; text-align:center;">Don't have an account? <a href="#/register">Register</a></p>
            </form>
        </div>
    `;

    document.getElementById('login-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        try {
            const res = await AuthService.login(email, password);
            AuthService.saveUser(res.user);
            showToast("Logged in successfully", "success");
            window.location.hash = '#/menu';
        } catch (err) {
            showToast(err.message, "danger");
        }
    });
}

function renderRegister() {
    app.innerHTML = `
        <div style="max-width: 400px; margin: 4rem auto;">
            <h1>Register</h1>
            <form id="register-form">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </form>
        </div>
    `;

    document.getElementById('register-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const data = {
            full_name: document.getElementById('full_name').value,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value
        };

        try {
            await AuthService.register(data);
            showToast("Registration successful! Please login.", "success");
            window.location.hash = '#/login';
        } catch (err) {
            showToast(err.message, "danger");
        }
    });
}

// Logout Listener
document.getElementById('logout-btn')?.addEventListener('click', (e) => {
    e.preventDefault();
    AuthService.logout();
});
