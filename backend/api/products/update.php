<?php
// backend/api/products/update.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){ // using POST for file upload support easier than PUT
    http_response_code(405);
    echo json_encode(["message" => "Method Not Allowed"]);
    exit();
}

$data = json_decode(file_get_contents("php://input")); 
// Note: processing multipart/form-data for files, so $_POST and $_FILES are used, not raw input stream usually.
// But if no file is uploaded, we might receive JSON. Let's support both or just Form Data for simplicity in consistency with Create.
// For consistency with Create, we assume Form Data.

if (isset($_POST["product_id"]) && isset($_POST["name"])) {
    
    $product_id = htmlspecialchars(strip_tags($_POST["product_id"]));
    $name = htmlspecialchars(strip_tags($_POST["name"]));
    $description = isset($_POST["description"]) ? htmlspecialchars(strip_tags($_POST["description"])) : "";
    $price = htmlspecialchars(strip_tags($_POST["price"]));
    $category_id = htmlspecialchars(strip_tags($_POST["category_id"]));
    $is_available = isset($_POST["is_available"]) ? $_POST["is_available"] : 1;
    
    $query = "UPDATE products SET name = :name, description = :description, price = :price, category_id = :category_id, is_available = :is_available";
    
    // Check if new image
    $image_update = false;
    $db_image_path = "";
    if(isset($_FILES["image"]) && $_FILES["image"]["size"] > 0){
        $target_dir = "../../uploads/products/";
        $original_filename = basename($_FILES["image"]["name"]);
        $imageFileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $new_filename = uniqid() . "." . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $db_image_path = "uploads/products/" . $new_filename;
            $query .= ", image_path = :image_path";
            $image_update = true;
        }
    }
    
    $query .= " WHERE product_id = :product_id";
    
    $stmt = $conn->prepare($query);
    
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":description", $description);
    $stmt->bindParam(":price", $price);
    $stmt->bindParam(":category_id", $category_id);
    $stmt->bindParam(":is_available", $is_available);
    $stmt->bindParam(":product_id", $product_id);
    
    if($image_update){
        $stmt->bindParam(":image_path", $db_image_path);
    }
    
    if($stmt->execute()){
        http_response_code(200);
        echo json_encode(["message" => "Product updated successfully."]);
    } else {
        http_response_code(503);
        echo json_encode(["message" => "Unable to update product."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data."]);
}
?>
