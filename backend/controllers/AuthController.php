<?php
/**
 * Food Ordering System - AuthController
 * Handles user registration, login, token generation, and authentication flows.
 *
 * Uses JWT for stateless authentication, secure password hashing,
 * input validation & sanitization.
 *
 * All responses are JSON via Response class.
 * Expects Request object to be available (usually injected or global).
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/password.php';
require_once __DIR__ . '/../utils/jwt.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../utils/sanitizer.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class AuthController
{
    private User $userModel;
    private Request $request;

    /**
     * Constructor - initializes dependencies
     * Request is expected to be passed or available globally
     *
     * @param Request|null $request Optional Request instance
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->userModel = new User();
    }

    /**
     * Handle user registration (POST /auth/register)
     *
     * @return never
     */
    public function register(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        // Validate input
        $errors = validate($data, [
            'full_name' => 'required|string|min:2|max:120',
            'email'     => 'required|email',
            'password'  => 'required|string|min:8',
            'phone'     => 'optional|string|min:9|max:15'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Registration failed');
        }

        // Sanitize inputs
        $cleanData = [
            'full_name' => sanitizeString($data['full_name']),
            'email'     => sanitizeEmail($data['email']),
            'password'  => $data['password'], // will be hashed - no need to sanitize
            'phone'     => sanitizeString($data['phone'] ?? '')
        ];

        // Check if email already exists
        if ($this->userModel->findByEmail($cleanData['email']) !== null) {
            Response::error('Email already registered', 409);
        }

        // Create user
        $success = $this->userModel->register($cleanData);

        if ($success) {
            Response::created(['message' => 'User registered successfully']);
        }

        Response::error('Registration failed. Please try again.', 500);
    }

    /**
     * Handle user login (POST /auth/login)
     *
     * @return never
     */
    public function login(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        // Validate input
        $errors = validate($data, [
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Login failed');
        }

        $email    = sanitizeEmail($data['email']);
        $password = $data['password'];

        // Authenticate
        $user = $this->userModel->login($email, $password);

        if (!$user) {
            Response::unauthorized('Invalid email or password');
        }

        // Generate JWT
        $token = generateToken([
            'sub'   => $user['id'],
            'role'  => $user['role_name'],
            'email' => $user['email'],
            'name'  => $user['full_name']
        ], 3600 * 24); // 24 hours

        // Remove sensitive data
        unset($user['password_hash']);

        Response::success([
            'token' => $token,
            'user'  => $user,
            'message' => 'Login successful'
        ]);
    }

    /**
     * Optional: Refresh JWT token (POST /auth/refresh)
     * Requires valid current token
     *
     * @return never
     */
    public function refreshToken(): never
    {
        // In real implementation, you would extract token from Authorization header
        // and verify it first (similar to AuthMiddleware logic)

        // For simplicity, assuming token is valid and we just issue new one
        // In production: validate old token, extract payload, issue new with fresh expiry

        $authHeader = $this->request->header('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/', $authHeader, $matches)) {
            Response::unauthorized('Token required');
        }

        $token = $matches[1];
        $payload = decodeToken($token);

        if (!$payload) {
            Response::unauthorized('Invalid or expired token');
        }

        // Issue new token with refreshed expiry
        $newToken = generateToken([
            'sub'   => $payload['sub'],
            'role'  => $payload['role'],
            'email' => $payload['email'],
            'name'  => $payload['name'] ?? ''
        ], 3600 * 24);

        Response::success([
            'token'   => $newToken,
            'message' => 'Token refreshed successfully'
        ]);
    }

    /**
     * Optional: Logout (POST /auth/logout)
     * In stateless JWT, usually just client-side token removal
     * Here we can return success message
     *
     * @return never
     */
    public function logout(): never
    {
        // In real app: could add token to blacklist (Redis, DB table)
        // For now, just success response

        Response::success(['message' => 'Logged out successfully']);
    }

    /*
     * Typical routing usage in routes/auth.php or index.php:
     *
     * $auth = new AuthController($request);
     *
     * $router->post('/auth/register', [$auth, 'register']);
     * $router->post('/auth/login',    [$auth, 'login']);
     * $router->post('/auth/refresh',  [$auth, 'refreshToken']);
     * $router->post('/auth/logout',   [$auth, 'logout']);
     */
}