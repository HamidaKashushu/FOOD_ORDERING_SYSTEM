<?php
// backend/api/orders/read.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$role = isset($_GET['role']) ? $_GET['role'] : 'customer'; // In production, verify this via session/token

$query = "SELECT o.order_id, o.user_id, o.total_amount, o.status, o.created_at, u.full_name 
          FROM orders o 
          JOIN users u ON o.user_id = u.user_id 
          WHERE 1=1";

if($role !== 'admin' && $user_id){
    $query .= " AND o.user_id = :user_id";
}

$query .= " ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);

if($role !== 'admin' && $user_id){
    $stmt->bindParam(':user_id', $user_id);
}

$stmt->execute();
$num = $stmt->rowCount();

if($num > 0){
    $orders_arr = array();
    $orders_arr["records"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        extract($row);
        
        // Fetch items for each order (could be optimized with a single JOIN but this is clearer for structure)
        $query_items = "SELECT oi.*, p.name, p.image_path 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.product_id 
                        WHERE oi.order_id = :order_id";
        $stmt_items = $conn->prepare($query_items);
        $stmt_items->bindParam(':order_id', $order_id);
        $stmt_items->execute();
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

        $order_item = array(
            "order_id" => $order_id,
            "user_id" => $user_id,
            "user_name" => $full_name,
            "total_amount" => $total_amount,
            "status" => $status,
            "date" => $created_at,
            "items" => $items
        );
        array_push($orders_arr["records"], $order_item);
    }
    http_response_code(200);
    echo json_encode($orders_arr);
} else {
    http_response_code(404);
    echo json_encode(["message" => "No orders found."]);
}
?>
