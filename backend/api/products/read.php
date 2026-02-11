<?php
// backend/api/products/read.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

// Build query
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE 1=1";

// Check for category filter
if(isset($_GET['category_id'])){
    $query .= " AND p.category_id = :category_id";
}

// Check for search
if(isset($_GET['s'])){
    $query .= " AND p.name LIKE :search_term";
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);

if(isset($_GET['category_id'])){
    $stmt->bindParam(':category_id', $_GET['category_id']);
}
if(isset($_GET['s'])){
    $search_term = "%" . $_GET['s'] . "%";
    $stmt->bindParam(':search_term', $search_term);
}

$stmt->execute();
$num = $stmt->rowCount();

if($num > 0){
    $products_arr = array();
    $products_arr["records"] = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $product_item = array(
            "product_id" => $product_id,
            "category_id" => $category_id,
            "category_name" => $category_name,
            "name" => $name,
            "description" => $description,
            "price" => $price,
            "image_path" => $image_path,
            "is_available" => $is_available
        );
        array_push($products_arr["records"], $product_item);
    }
    http_response_code(200);
    echo json_encode($products_arr);
} else {
    http_response_code(404);
    echo json_encode(["message" => "No products found."]);
}
?>
