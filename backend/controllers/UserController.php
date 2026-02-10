<?php
/**
 * Food Ordering System - UserController
 * Handles user profile management, updates, admin user listing,
 * user deletion, and role assignment.
 *
 * Protected routes should be wrapped with AuthMiddleware + RoleMiddleware('admin')
 * for admin-only actions (getAllUsers, deleteUser, assignRole).
 *
 * All responses are standardized JSON via Response class.
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../utils/sanitizer.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../utils/password.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class UserController
{
    private User $userModel;
    private Role $roleModel;
    private Request $request;

    /**
     * Constructor - initializes models and request
     *
     * @param Request|null $request Optional Request instance
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->userModel = new User();
        $this->roleModel = new Role();
    }

    /**
     * Get authenticated user's profile (GET /profile or /users/me)
     *
     * @param int $userId Authenticated user ID (from JWT/middleware)
     * @return never
     */
    public function getProfile(int $userId): never
    {
        $user = $this->userModel->findById($userId);

        if (!$user) {
            Response::notFound('User not found');
        }

        // Remove sensitive fields
        unset($user['password_hash']);

        Response::success($user, 'Profile retrieved successfully');
    }

    /**
     * Update authenticated user's profile (PUT/PATCH /profile)
     *
     * @param int $userId Authenticated user ID
     * @return never
     */
    public function updateProfile(int $userId): never
    {
        if (!$this->request->isMethod('PUT') && !$this->request->isMethod('PATCH')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        // Validation rules
        $rules = [
            'full_name' => 'optional|string|min:2|max:120',
            'phone'     => 'optional|string|min:9|max:15',
            'password'  => 'optional|string|min:8'
        ];

        // Email update is restricted or requires verification in real apps
        if (isset($data['email'])) {
            Response::error('Email cannot be updated through this endpoint', 403);
        }

        $errors = validate($data, $rules);

        if (!empty($errors)) {
            Response::validation($errors, 'Profile update failed');
        }

        // Sanitize inputs
        $cleanData = [];
        if (isset($data['full_name'])) {
            $cleanData['full_name'] = sanitizeString($data['full_name']);
        }
        if (isset($data['phone'])) {
            $cleanData['phone'] = sanitizeString($data['phone']);
        }
        if (!empty($data['password'])) {
            $cleanData['password'] = $data['password']; // will be hashed in model
        }

        if (empty($cleanData)) {
            Response::error('No fields to update', 400);
        }

        $success = $this->userModel->update($userId, $cleanData);

        if ($success) {
            $updatedUser = $this->userModel->findById($userId);
            unset($updatedUser['password_hash']);
            Response::success($updatedUser, 'Profile updated successfully');
        }

        Response::error('Failed to update profile', 500);
    }

    /**
     * Get all users (admin only) - GET /admin/users
     *
     * @return never
     */
    public function getAllUsers(): never
    {
        $users = $this->userModel->getAll();

        // Remove sensitive data from each user
        foreach ($users as &$user) {
            unset($user['password_hash']);
        }

        Response::success($users, 'Users retrieved successfully');
    }

    /**
     * Create a new user (admin only) - POST /admin/users
     *
     * @return never
     */
    public function createUser(): never
    {
        $data = $this->request->all();

        $errors = validate($data, [
            'full_name' => 'required|string|min:2|max:120',
            'email'     => 'required|email',
            'password'  => 'required|string|min:8',
            'role'      => 'optional|string',
            'status'    => 'optional|in:active,blocked'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'User creation failed');
        }

        if ($this->userModel->findByEmail($data['email'])) {
            Response::error('Email already exists', 409);
        }

        // Get role ID
        $roleName = $data['role'] ?? 'customer';
        $role = $this->roleModel->findByName($roleName);
        if (!$role) {
            $role = $this->roleModel->findByName('customer');
        }

        $userData = [
            'full_name'     => sanitizeString($data['full_name']),
            'email'         => sanitizeEmail($data['email']),
            'password_hash' => hashPassword($data['password']),
            'role_id'       => $role['id'],
            'status'        => $data['status'] ?? 'active'
        ];

        if ($this->userModel->create($userData)) {
            Response::created(['message' => 'User created successfully']);
        }

        Response::error('Failed to create user', 500);
    }

    /**
     * Update any user (admin only) - PUT /admin/users/{id}
     *
     * @param int $id User ID
     * @return never
     */
    public function updateUser(int $id): never
    {
        $data = $this->request->all(); // Supports PUT/PATCH via form override

        $errors = validate($data, [
            'full_name' => 'optional|string|min:2|max:120',
            'email'     => 'optional|email',
            'role'      => 'optional|string',
            'status'    => 'optional|in:active,blocked'
            // Password update by admin can be added if needed, usually omitted here to keep it simple
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'User update failed');
        }

        $updates = [];
        if (isset($data['full_name'])) $updates['full_name'] = sanitizeString($data['full_name']);
        if (isset($data['email'])) $updates['email'] = sanitizeEmail($data['email']);
        if (isset($data['phone'])) $updates['phone'] = sanitizeString($data['phone']);
        if (isset($data['status'])) $updates['status'] = $data['status'];

        // Role update
        if (isset($data['role'])) {
            $role = $this->roleModel->findByName($data['role']);
            if ($role) $updates['role_id'] = $role['id'];
        }

        // Password update (if provided)
        if (!empty($data['password'])) {
            $updates['password_hash'] = hashPassword($data['password']);
        }

        if (empty($updates)) {
            Response::error('No fields to update', 400);
        }

        if ($this->userModel->update($id, $updates)) {
            Response::success(['message' => 'User updated successfully']);
        }

        Response::error('Failed to update user', 500);
    }

    /**
     * Delete a user (admin only) - DELETE /admin/users/{id}
     *
     * @param int $userId User ID to delete
     * @return never
     */
    public function deleteUser(int $userId): never
    {
        // Prevent self-deletion or admin deletion (optional protection)
        // In real app: add check if $userId === authenticated user → forbidden

        $success = $this->userModel->delete($userId);

        if ($success) {
            Response::success(['message' => 'User deleted successfully']);
        }

        Response::error('Failed to delete user', 500);
    }

    /**
     * Assign role to user (admin only) - POST /admin/users/{id}/role
     *
     * @param int    $userId   User ID
     * @param string $roleName Role name (e.g. 'admin', 'customer')
     * @return never
     */
    public function assignRole(int $userId, string $roleName): never
    {
        if (empty($roleName)) {
            Response::error('Role name is required', 400);
        }

        $role = $this->roleModel->findByName($roleName);
        if (!$role) {
            Response::error("Role '$roleName' not found", 404);
        }

        $success = $this->roleModel->assignRoleToUser($userId, $role['id']);

        if ($success) {
            Response::success(['message' => "Role '$roleName' assigned successfully"]);
        }

        Response::error('Failed to assign role', 500);
    }

    /*
     * Typical routing usage in routes/users.php or index.php:
     *
     * $userCtrl = new UserController($request);
     *
     * // Protected user routes (after AuthMiddleware)
     * $router->get('/profile', fn() => $userCtrl->getProfile($authenticatedUserId));
     * $router->put('/profile', fn() => $userCtrl->updateProfile($authenticatedUserId));
     *
     * // Admin-only routes (after AuthMiddleware + RoleMiddleware('admin'))
     * $router->get('/admin/users',      [$userCtrl, 'getAllUsers']);
     * $router->delete('/admin/users/{id}', fn($id) => $userCtrl->deleteUser((int)$id));
     * $router->post('/admin/users/{id}/role', fn($id) => $userCtrl->assignRole((int)$id, $request->body('role')));
     */
}