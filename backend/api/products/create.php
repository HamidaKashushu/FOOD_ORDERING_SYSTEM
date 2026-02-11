<?php
// backend/api/products/create.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

// Check if image file is a actual image or fake image
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(["message" => "Method Not Allowed"]);
    exit();
}

$target_dir = "../../uploads/products/";

// Check if POST data contains files
if (isset($_FILES["image"]) && isset($_POST["name"]) && isset($_POST["price"]) && isset($_POST["category_id"])) {
    
    $name = htmlspecialchars(strip_tags($_POST["name"]));
    $description = isset($_POST["description"]) ? htmlspecialchars(strip_tags($_POST["description"])) : "";
    $price = htmlspecialchars(strip_tags($_POST["price"]));
    $category_id = htmlspecialchars(strip_tags($_POST["category_id"]));
    
    // Image handling
    $original_filename = basename($_FILES["image"]["name"]);
    $imageFileType = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    
    // Generate unique name
    $new_filename = uniqid() . "." . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Validate image
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if($check === false) {
        http_response_code(400);
        echo json_encode(["message" => "File is not an image."]);
        exit();
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" && $imageFileType != "webp") {
        http_response_code(400);
        echo json_encode(["message" => "Sorry, only JPG, JPEG, PNG, GIF, & WEBP files are allowed."]);
        exit();
    }
    
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // File uploaded, now insert into DB
        // Save relative path: "backend/uploads/products/filename.jpg" or just "uploads/products/..."
        // The prompt asked for "backend/uploads/products", typically we store relative to web root or API root.
        // Let's store relative to the API root concept or just the filename if consistent, but full path is safer to return.
        // I will store "uploads/products/$new_filename" relative to the backend folder.
        
        $db_image_path = "uploads/products/" . $new_filename;
        
        $query = "INSERT INTO products (category_id, name, description, price, image_path) VALUES (:category_id, :name, :description, :price, :image_path)";
        $stmt = $conn->prepare($query);
        
        $stmt->bindParam(":category_id", $category_id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":price", $price);
        $stmt->bindParam(":image_path", $db_image_path);
        
        if($stmt->execute()){
            http_response_code(201);
            echo json_encode(["message" => "Product created successfully."]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "Unable to create product."]);
        }
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Sorry, there was an error uploading your file."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data. Name, price, category, and image are required."]);
}
?>
