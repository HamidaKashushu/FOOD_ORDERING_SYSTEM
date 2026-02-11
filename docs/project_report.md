# D-FOOD Project Report

## 1. System Overview
**D-FOOD** is a web-based food ordering system designed for the Tanzanian market. It allows customers to browse authentic food categories, view product details, add items to a cart, and place orders. Administrators can manage the product catalog and view incoming orders. The system is built using a **PHP** backend and a **Vanilla JavaScript** frontend, utilizing a **MySQL** database for data storage.

### Key Features
- **User Authentication**: Secure registration and login for customers and admins.
- **Product Catalog**: Browse food items by category with search functionality.
- **Shopping Cart**: Add/remove items and calculate totals dynamically.
- **Order Management**: Customers can place orders; Admins can view all orders.
- **Admin Panel**: Secure interface for adding new products and uploading images.
- **Responsive Design**: Optimized for mobile and desktop screens.

## 2. System Architecture
The system follows a **Three-Tier Architecture**:
1.  **Presentation Layer (Frontend)**: HTML5, CSS3, Vanilla JavaScript. Handles user interaction and communicates with the backend via RESTful API (Fetch).
2.  **Application Layer (Backend)**: PHP. Processes API requests, handles business logic, and interacts with the database.
3.  **Data Layer (Database)**: MySQL. Stores user data, products, orders, and payments.

### Directory Structure
```
d_food/
├── backend/
│   ├── api/            # JSON API Endpoints
│   │   ├── auth/       # Login, Register
│   │   ├── categories/ # Read categories
│   │   ├── orders/     # Create, Read orders
│   │   └── products/   # CRUD operations
│   ├── config/         # Database connection & CORS
│   └── uploads/        # Product images
├── database/
│   └── schema.sql      # Database structure
├── docs/               # Documentation
└── frontend/
    ├── assets/         # CSS, JS, Images
    ├── index.html      # SPA Entry Point
    └── pages/          # (Optional html templates)
```

## 3. Database Design
The database is normalized to **Third Normal Form (3NF)** and consists of 6 tables.

### E-R Diagram Description
- **Users**: One-to-Many with Orders.
- **Categories**: One-to-Many with Products.
- **Products**: Many-to-Many with Orders (via Order_Items).
- **Orders**: One-to-One with Payments.

### Tables
1.  **users**: `user_id`, `full_name`, `email`, `password_hash`, `role`
2.  **categories**: `category_id`, `name`, `description`
3.  **products**: `product_id`, `category_id`, `name`, `price`, `image_path`
4.  **orders**: `order_id`, `user_id`, `total_amount`, `status`
5.  **order_items**: `order_item_id`, `order_id`, `product_id`, `quantity`, `unit_price`
6.  **payments**: `payment_id`, `order_id`, `payment_method`, `status`

## 4. Implementation Details
### Backend
- **Language**: PHP 7.4+
- **Security**: 
    - `password_hash()` for secure credential storage.
    - Prepared Statements (`PDO`) to prevent SQL Injection.
    - `htmlspecialchars()` for XSS protection.
- **API**: Returns JSON responses with standard HTTP status codes (200, 201, 400, 401, 500).

### Frontend
- **Framework-less**: Pure Vanilla JS using ES6+ features (`async/await`, modules).
- **SPA Routing**: Custom hash-based router (`#/menu`, `#/login`) in `app.js`.
- **State Management**: `localStorage` used for Cart and User Session persistence.

## 5. Usage Guide
1.  **Installation**:
    - Move the `d_food` folder to XAMPP `htdocs`.
    - Import `database/schema.sql` into phpMyAdmin (create DB `d_food`).
    - Adjust `backend/config/db.php` if your MySQL credentials differ from root/empty.
2.  **Running**:
    - Open browser and go to `http://localhost/d_food/frontend/`.
3.  **Admin Access**:
    - Manually change a registered user's role to 'admin' in the database or seed an admin user.
    - Login to access the Admin Panel.

## 6. Screenshots
*(Placeholders for actual screenshots)*
- **Home Page**: Hero section with popular categories.
- **Menu**: Grid view of food items with search bar.
- **Admin Dashboard**: Product management forms.
