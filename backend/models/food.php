<?php

// backend/models/Food.php

class Food {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Get all available foods with optional search and category filter
     *
     * @param string $search
     * @param int|null $category_id
     * @return array
     */
    public function getAll(string $search = '', ?int $category_id = null): array {
        $sql = "
            SELECT 
                f.food_id,
                f.category_id,
                c.category_name,
                f.food_name,
                f.description,
                f.price,
                f.image_url,
                f.is_available,
                f.created_at
            FROM foods f
            LEFT JOIN categories c ON f.category_id = c.category_id
            WHERE f.is_available = 1
        ";
        $params = [];

        if ($search !== '') {
            $sql .= " AND f.food_name LIKE :search";
            $params[':search'] = '%' . trim($search) . '%';
        }

        if ($category_id !== null && $category_id > 0) {
            $sql .= " AND f.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }

        $sql .= " ORDER BY f.food_name ASC";

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Food::getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single food by ID with category name
     *
     * @param int $food_id
     * @return array|null
     */
    public function getById(int $food_id): ?array {
        if ($food_id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    f.food_id,
                    f.category_id,
                    c.category_name,
                    f.food_name,
                    f.description,
                    f.price,
                    f.image_url,
                    f.is_available,
                    f.created_at
                FROM foods f
                LEFT JOIN categories c ON f.category_id = c.category_id
                WHERE f.food_id = ?
                LIMIT 1
            ");
            $stmt->execute([$food_id]);
            $food = $stmt->fetch(PDO::FETCH_ASSOC);

            return $food ?: null;
        } catch (PDOException $e) {
            error_log("Food::getById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a new food item
     *
     * @param int $category_id
     * @param string $food_name
     * @param string $description
     * @param float $price
     * @param string $image_url
     * @param bool $is_available
     * @return int|null New food_id or null on failure
     */
    public function create(
        int $category_id,
        string $food_name,
        string $description,
        float $price,
        string $image_url,
        bool $is_available = true
    ): ?int {
        if ($category_id <= 0 || empty(trim($food_name)) || $price < 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO foods 
                (category_id, food_name, description, price, image_url, is_available, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $success = $stmt->execute([
                $category_id,
                trim($food_name),
                trim($description),
                $price,
                trim($image_url),
                $is_available ? 1 : 0
            ]);

            return $success ? (int)$this->conn->lastInsertId() : null;
        } catch (PDOException $e) {
            error_log("Food::create error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update food details
     * Only provided fields are updated
     *
     * @param int $food_id
     * @param int|null $category_id
     * @param string|null $food_name
     * @param string|null $description
     * @param float|null $price
     * @param string|null $image_url
     * @param bool|null $is_available
     * @return bool
     */
    public function update(
        int $food_id,
        ?int $category_id = null,
        ?string $food_name = null,
        ?string $description = null,
        ?float $price = null,
        ?string $image_url = null,
        ?bool $is_available = null
    ): bool {
        if ($food_id <= 0) {
            return false;
        }

        $updates = [];
        $params = [];

        if ($category_id !== null && $category_id > 0) {
            $updates[] = "category_id = :category_id";
            $params[':category_id'] = $category_id;
        }
        if ($food_name !== null && trim($food_name) !== '') {
            $updates[] = "food_name = :food_name";
            $params[':food_name'] = trim($food_name);
        }
        if ($description !== null) {
            $updates[] = "description = :description";
            $params[':description'] = trim($description);
        }
        if ($price !== null && $price >= 0) {
            $updates[] = "price = :price";
            $params[':price'] = $price;
        }
        if ($image_url !== null) {
            $updates[] = "image_url = :image_url";
            $params[':image_url'] = trim($image_url);
        }
        if ($is_available !== null) {
            $updates[] = "is_available = :is_available";
            $params[':is_available'] = $is_available ? 1 : 0;
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE foods SET " . implode(', ', $updates) . " WHERE food_id = :food_id";
        $params[':food_id'] = $food_id;

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Food::update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a food item
     *
     * @param int $food_id
     * @return bool
     */
    public function delete(int $food_id): bool {
        if ($food_id <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM foods WHERE food_id = ?");
            $stmt->execute([$food_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Food::delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all food categories
     *
     * @return array
     */
    public function getAllCategories(): array {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    category_id,
                    category_name,
                    description,
                    created_at
                FROM categories
                ORDER BY category_name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Food::getAllCategories error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create a new category
     *
     * @param string $category_name
     * @param string $description
     * @return int|null New category_id
     */
    public function createCategory(string $category_name, string $description = ''): ?int {
        if (empty(trim($category_name))) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO categories (category_name, description, created_at)
                VALUES (?, ?, NOW())
            ");
            $success = $stmt->execute([trim($category_name), trim($description)]);

            return $success ? (int)$this->conn->lastInsertId() : null;
        } catch (PDOException $e) {
            error_log("Food::createCategory error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update category details
     *
     * @param int $category_id
     * @param string|null $category_name
     * @param string|null $description
     * @return bool
     */
    public function updateCategory(
        int $category_id,
        ?string $category_name = null,
        ?string $description = null
    ): bool {
        if ($category_id <= 0) {
            return false;
        }

        $updates = [];
        $params = [];

        if ($category_name !== null && trim($category_name) !== '') {
            $updates[] = "category_name = :category_name";
            $params[':category_name'] = trim($category_name);
        }
        if ($description !== null) {
            $updates[] = "description = :description";
            $params[':description'] = trim($description);
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE categories SET " . implode(', ', $updates) . " WHERE category_id = :category_id";
        $params[':category_id'] = $category_id;

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Food::updateCategory error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a category
     *
     * @param int $category_id
     * @return bool
     */
    public function deleteCategory(int $category_id): bool {
        if ($category_id <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Food::deleteCategory error: " . $e->getMessage());
            return false;
        }
    }
}