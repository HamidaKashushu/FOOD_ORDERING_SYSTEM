<?php
// backend/api/products/delete.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->product_id)){
    $product_id = htmlspecialchars(strip_tags($data->product_id));
    
    $query = "DELETE FROM products WHERE product_id = :product_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":product_id", $product_id);
    
    if($stmt->execute()){
        http_response_code(200);
        echo json_encode(["message" => "Product deleted."]);
    } else {
        http_response_code(503);
        echo json_encode(["message" => "Unable to delete product."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Product ID is missing."]);
}
?>
