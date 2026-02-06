<?php
/**
 * Food Ordering System - CategoryController
 * Handles category listing, CRUD operations, search, and status management.
 *
 * Public read operations (getAll, getById, search) are available to all users.
 * Write operations (create, update, delete, setStatus) are restricted to admins.
 *
 * All responses are standardized JSON via Response class.
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../utils/sanitizer.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class CategoryController
{
    private Category $categoryModel;
    private Request $request;

    /**
     * Constructor - initializes model and request
     *
     * @param Request|null $request Optional Request instance
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->categoryModel = new Category();
    }

    /**
     * Get all categories (GET /categories)
     *
     * @return never
     */
    public function getAll(): never
    {
        $categories = $this->categoryModel->getAll();

        Response::success($categories, 'Categories retrieved successfully');
    }

    /**
     * Get single category by ID (GET /categories/{id})
     *
     * @param int $id Category ID
     * @return never
     */
    public function getById(int $id): never
    {
        $category = $this->categoryModel->findById($id);

        if (!$category) {
            Response::notFound('Category not found');
        }

        Response::success($category, 'Category retrieved successfully');
    }

    /**
     * Create new category (POST /admin/categories) - admin only
     *
     * @return never
     */
    public function create(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'name'        => 'required|string|min:2|max:100',
            'description' => 'optional|string|max:500',
            'status'      => 'optional|in:active,inactive'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Category creation failed');
        }

        $cleanData = [
            'name'        => sanitizeString($data['name']),
            'description' => sanitizeString($data['description'] ?? ''),
            'status'      => $data['status'] ?? 'active'
        ];

        $success = $this->categoryModel->create($cleanData);

        if ($success) {
            Response::created(['message' => 'Category created successfully']);
        }

        Response::error('Failed to create category', 500);
    }

    /**
     * Update existing category (PUT/PATCH /admin/categories/{id}) - admin only
     *
     * @param int $id Category ID
     * @return never
     */
    public function update(int $id): never
    {
        if (!$this->request->isMethod('PUT') && !$this->request->isMethod('PATCH')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'name'        => 'optional|string|min:2|max:100',
            'description' => 'optional|string|max:500',
            'status'      => 'optional|in:active,inactive'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Category update failed');
        }

        $cleanData = [];
        if (isset($data['name'])) {
            $cleanData['name'] = sanitizeString($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $cleanData['description'] = sanitizeString($data['description']);
        }
        if (isset($data['status'])) {
            $cleanData['status'] = $data['status'];
        }

        if (empty($cleanData)) {
            Response::error('No fields to update', 400);
        }

        $success = $this->categoryModel->update($id, $cleanData);

        if ($success) {
            $updated = $this->categoryModel->findById($id);
            Response::success($updated, 'Category updated successfully');
        }

        Response::error('Failed to update category', 500);
    }

    /**
     * Delete category (DELETE /admin/categories/{id}) - admin only
     *
     * @param int $id Category ID
     * @return never
     */
    public function delete(int $id): never
    {
        $category = $this->categoryModel->findById($id);
        if (!$category) {
            Response::notFound('Category not found');
        }

        $success = $this->categoryModel->delete($id);

        if ($success) {
            Response::success(['message' => 'Category deleted successfully']);
        }

        Response::error('Failed to delete category', 500);
    }

    /**
     * Search categories by keyword (GET /categories/search?q=...)
     *
     * @return never
     */
    public function search(): never
    {
        $keyword = $this->request->query('q', '');

        if (empty(trim($keyword))) {
            Response::error('Search keyword is required', 400);
        }

        $results = $this->categoryModel->search($keyword);

        Response::success($results, 'Search results');
    }

    /**
     * Update category status (PATCH /admin/categories/{id}/status) - admin only
     *
     * @param int    $id     Category ID
     * @param string $status New status ('active' or 'inactive')
     * @return never
     */
    public function setStatus(int $id, string $status): never
    {
        if (!in_array($status, ['active', 'inactive'])) {
            Response::error('Invalid status value. Use "active" or "inactive"', 400);
        }

        $success = $this->categoryModel->setStatus($id, $status);

        if ($success) {
            Response::success(['message' => "Category status updated to $status"]);
        }

        Response::error('Failed to update category status', 500);
    }

    /*
     * Typical routing usage in routes/categories.php or index.php:
     *
     * $catCtrl = new CategoryController($request);
     *
     * // Public routes
     * $router->get('/categories',          [$catCtrl, 'getAll']);
     * $router->get('/categories/{id}',     fn($id) => $catCtrl->getById((int)$id));
     * $router->get('/categories/search',   [$catCtrl, 'search']);
     *
     * // Admin-only routes (after AuthMiddleware + RoleMiddleware('admin'))
     * $router->post('/admin/categories',   [$catCtrl, 'create']);
     * $router->put('/admin/categories/{id}', fn($id) => $catCtrl->update((int)$id));
     * $router->delete('/admin/categories/{id}', fn($id) => $catCtrl->delete((int)$id));
     * $router->patch('/admin/categories/{id}/status', fn($id) => $catCtrl->setStatus((int)$id, $request->body('status')));
     */
}