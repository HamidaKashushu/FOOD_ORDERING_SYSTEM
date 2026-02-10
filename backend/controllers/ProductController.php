<?php
/**
 * Food Ordering System - ProductController
 * Handles product listing, CRUD operations, category filtering,
 * search, and status management.
 *
 * Public read operations (getAll, getById, getByCategory, search) are available to all users.
 * Write operations (create, update, delete, setStatus) are restricted to admins.
 *
 * All responses are standardized JSON via Response class.
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../utils/sanitizer.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class ProductController
{
    private Product $productModel;
    private Category $categoryModel;
    private Request $request;

    /**
     * Constructor - initializes models and request
     *
     * @param Request|null $request Optional Request instance
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->productModel = new Product();
        $this->categoryModel = new Category();
    }

    /**
     * Get all products (GET /products)
     *
     * @return never
     */
    public function getAll(): never
    {
        $products = $this->productModel->getAll();

        Response::success($products, 'Products retrieved successfully');
    }

    /**
     * Get single product by ID (GET /products/{id})
     *
     * @param int $id Product ID
     * @return never
     */
    public function getById(int $id): never
    {
        $product = $this->productModel->findById($id);

        if (!$product) {
            Response::notFound('Product not found');
        }

        Response::success($product, 'Product retrieved successfully');
    }

    /**
     * Get products by category (GET /categories/{categoryId}/products)
     *
     * @param int $categoryId Category ID
     * @return never
     */
    public function getByCategory(int $categoryId): never
    {
        $category = $this->categoryModel->findById($categoryId);
        if (!$category) {
            Response::notFound('Category not found');
        }

        $products = $this->productModel->getByCategory($categoryId);

        Response::success([
            'category' => $category,
            'products' => $products
        ], 'Products in category retrieved');
    }

    /**
     * Search products by keyword (GET /products/search?q=...)
     *
     * @return never
     */
    public function search(): never
    {
        $keyword = $this->request->query('q', '');

        if (empty(trim($keyword))) {
            Response::error('Search keyword is required', 400);
        }

        $results = $this->productModel->search($keyword);

        Response::success($results, 'Search results');
    }

    /**
     * Create new product (POST /admin/products) - admin only
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
            'name'         => 'required|string|min:2|max:150',
            'description'  => 'optional|string|max:1000',
            'price'        => 'required|numeric|min:0.01',
            'category_id'  => 'required|numeric',
            'image'        => 'optional|string|max:255',
            'stock'        => 'optional|numeric|min:0',
            'status'       => 'optional|in:available,unavailable'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Product creation failed');
        }

        // Verify category exists
        if ($this->categoryModel->findById((int)$data['category_id']) === null) {
            Response::error('Invalid category ID', 400);
        }

        $imagePath = null;
        if ($file = $this->request->file('image')) {
            try {
                $imagePath = uploadImage($file, 'uploads/products');
            } catch (Exception $e) {
                Response::error('Image upload failed: ' . $e->getMessage(), 400);
            }
        }

        $cleanData = [
            'name'         => sanitizeString($data['name']),
            'description'  => sanitizeString($data['description'] ?? ''),
            'price'        => (float)$data['price'],
            'category_id'  => (int)$data['category_id'],
            'image'        => $imagePath, // Store path
            'stock'        => (int)($data['stock'] ?? 0),
            'status'       => $data['status'] ?? 'available'
        ];

        $success = $this->productModel->create($cleanData);

        if ($success) {
            Response::created(['message' => 'Product created successfully']);
        }

        Response::error('Failed to create product', 500);
    }

    /**
     * Update existing product (PUT/PATCH /admin/products/{id}) - admin only
     *
     * @param int $id Product ID
     * @return never
     */
    public function update(int $id): never
    {
        if (!$this->request->isMethod('PUT') && !$this->request->isMethod('PATCH')) {
            Response::error('Method not allowed', 405);
        }

        $product = $this->productModel->findById($id);
        if (!$product) {
            Response::notFound('Product not found');
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'name'         => 'optional|string|min:2|max:150',
            'description'  => 'optional|string|max:1000',
            'price'        => 'optional|numeric|min:0.01',
            'category_id'  => 'optional|numeric',
            'image'        => 'optional|string|max:255',
            'stock'        => 'optional|numeric|min:0',
            'status'       => 'optional|in:available,unavailable'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Product update failed');
        }

        $cleanData = [];
        if (isset($data['name'])) {
            $cleanData['name'] = sanitizeString($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $cleanData['description'] = sanitizeString($data['description']);
        }
        if (isset($data['price'])) {
            $cleanData['price'] = (float)$data['price'];
        }
        if (isset($data['category_id'])) {
            if ($this->categoryModel->findById((int)$data['category_id']) === null) {
                Response::error('Invalid category ID', 400);
            }
            $cleanData['category_id'] = (int)$data['category_id'];
        }
        if ($file = $this->request->file('image')) {
            try {
                $cleanData['image'] = uploadImage($file, 'uploads/products');
                
                // Optional: Delete old image if exists (not implemented for simplicity)
            } catch (Exception $e) {
                Response::error('Image upload failed: ' . $e->getMessage(), 400);
            }
        } elseif (array_key_exists('image', $data) && empty($data['image'])) {
            // Allow clearing image by sending empty string? Or keep it?
            // For now, if string 'image' is sent, we sanitize it (maybe URL update?)
             $cleanData['image'] = sanitizeString($data['image']);
        }
        if (isset($data['stock'])) {
            $cleanData['stock'] = (int)$data['stock'];
        }
        if (isset($data['status'])) {
            $cleanData['status'] = $data['status'];
        }

        if (empty($cleanData)) {
            Response::error('No fields to update', 400);
        }

        $success = $this->productModel->update($id, $cleanData);

        if ($success) {
            $updated = $this->productModel->findById($id);
            Response::success($updated, 'Product updated successfully');
        }

        Response::error('Failed to update product', 500);
    }

    /**
     * Delete product (DELETE /admin/products/{id}) - admin only
     *
     * @param int $id Product ID
     * @return never
     */
    public function delete(int $id): never
    {
        $product = $this->productModel->findById($id);
        if (!$product) {
            Response::notFound('Product not found');
        }

        $success = $this->productModel->delete($id);

        if ($success) {
            Response::success(['message' => 'Product deleted successfully']);
        }

        Response::error('Failed to delete product', 500);
    }

    /**
     * Update product status (PATCH /admin/products/{id}/status) - admin only
     *
     * @param int    $id     Product ID
     * @param string $status New status ('available' or 'unavailable')
     * @return never
     */
    public function setStatus(int $id, string $status): never
    {
        if (!in_array($status, ['available', 'unavailable'])) {
            Response::error('Invalid status value. Use "available" or "unavailable"', 400);
        }

        $product = $this->productModel->findById($id);
        if (!$product) {
            Response::notFound('Product not found');
        }

        $success = $this->productModel->setStatus($id, $status);

        if ($success) {
            Response::success(['message' => "Product status updated to $status"]);
        }

        Response::error('Failed to update product status', 500);
    }

    /*
     * Typical routing usage in routes/products.php or index.php:
     *
     * $prodCtrl = new ProductController($request);
     *
     * // Public routes
     * $router->get('/products',              [$prodCtrl, 'getAll']);
     * $router->get('/products/{id}',         fn($id) => $prodCtrl->getById((int)$id));
     * $router->get('/categories/{id}/products', fn($id) => $prodCtrl->getByCategory((int)$id));
     * $router->get('/products/search',       [$prodCtrl, 'search']);
     *
     * // Admin-only routes (after AuthMiddleware + RoleMiddleware('admin'))
     * $router->post('/admin/products',       [$prodCtrl, 'create']);
     * $router->put('/admin/products/{id}',   fn($id) => $prodCtrl->update((int)$id));
     * $router->delete('/admin/products/{id}', fn($id) => $prodCtrl->delete((int)$id));
     * $router->patch('/admin/products/{id}/status', fn($id) => $prodCtrl->setStatus((int)$id, $request->body('status')));
     */
}