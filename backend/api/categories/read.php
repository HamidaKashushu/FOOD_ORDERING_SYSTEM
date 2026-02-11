<?php
// backend/api/categories/read.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

$query = "SELECT * FROM categories ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute();

$num = $stmt->rowCount();

if($num > 0){
    $categories_arr = array();
    $categories_arr["records"] = array(); // standard JSON structure

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        $category_item = array(
            "category_id" => $category_id,
            "name" => $name,
            "description" => $description
        );
        array_push($categories_arr["records"], $category_item);
    }
    http_response_code(200);
    echo json_encode($categories_arr);
} else {
    http_response_code(404); // Not found, or just empty
    echo json_encode(array("message" => "No categories found."));
}
?>
