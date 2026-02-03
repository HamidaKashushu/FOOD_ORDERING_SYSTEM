<?php

// backend/routes/auth.php
// Authentication endpoints for Food Ordering System

if (!isset($conn) || !$conn instanceof PDO) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not available'
    ]);
    exit;
}

// Only allow POST requests for auth actions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Get action from query string
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {
    case 'register':
        handleRegister($conn);
        break;

    case 'login':
        handleLogin($conn);
        break;

    case 'logout':
        handleLogout();
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        exit;
}

function handleRegister(PDO $conn) {
    try {
        // Read JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !is_array($input)) {
            throw new Exception('Invalid JSON input', 400);
        }

        $full_name = trim($input['full_name'] ?? '');
        $email     = trim($input['email']     ?? '');
        $phone     = trim($input['phone']     ?? '');
        $password  = $input['password']       ?? '';

        // Validation
        if (empty($full_name) || empty($email) || empty($password)) {
            throw new Exception('Full name, email and password are required', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format', 400);
        }

        if (strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters', 400);
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Email already registered', 409);
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert new user (role_id = 2 → customer)
        $stmt = $conn->prepare("
            INSERT INTO users (full_name, email, phone, password_hash, role_id, created_at)
            VALUES (?, ?, ?, ?, 2, NOW())
        ");

        $success = $stmt->execute([$full_name, $email, $phone, $passwordHash]);

        if (!$success) {
            throw new Exception('Failed to create user account', 500);
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'email' => $email,
                'full_name' => $full_name
            ]
        ]);

    } catch (Exception $e) {
        $code = $e->getCode() ?: 500;
        if ($code < 400 || $code > 599) $code = 500;

        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function handleLogin(PDO $conn) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !is_array($input)) {
            throw new Exception('Invalid JSON input', 400);
        }

        $email    = trim($input['email']    ?? '');
        $password = $input['password']      ?? '';

        if (empty($email) || empty($password)) {
            throw new Exception('Email and password are required', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format', 400);
        }

        // Find user
        $stmt = $conn->prepare("
            SELECT id, full_name, email, phone, password_hash, role_id
            FROM users 
            WHERE email = ? 
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('Invalid email or password', 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid email or password', 401);
        }

        // Remove sensitive data
        unset($user['password_hash']);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => $user
        ]);

    } catch (Exception $e) {
        $code = $e->getCode() ?: 500;
        if ($code < 400 || $code > 599) $code = 500;

        http_response_code($code);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function handleLogout() {
    // For stateless JWT or token-based auth this would invalidate token
    // Here we just return success message (session-less API)
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful'
    ]);
}