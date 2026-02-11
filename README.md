# D-FOOD

## Setup Instructions

1.  **Database Setup**:
    - Open PHPMyAdmin (http://localhost/phpmyadmin).
    - Create a database named `d_food`.
    - Import the file `database/schema.sql`.

2.  **Configuration**:
    - Ensure your XAMPP Apache and MySQL services are running.
    - The project is configured for `localhost` with user `root` and no password. Update `backend/config/db.php` if needed.

3.  **Run**:
    - Open your browser and navigate to: `http://localhost/d_food/frontend/`

## Admin Access
To access the admin panel, register a new user, then go to your database `users` table and change their `role` from `customer` to `admin`. Then log out and log back in.
