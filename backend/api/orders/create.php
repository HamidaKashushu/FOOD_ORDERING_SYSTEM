<?php
// backend/api/orders/create.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->user_id) &&
    !empty($data->items) &&
    is_array($data->items) &&
    !empty($data->total_amount) &&
    !empty($data->delivery_address)
){
    try {
        $conn->beginTransaction();

        // 1. Create Order
        $query = "INSERT INTO orders (user_id, total_amount, delivery_address, status) VALUES (:user_id, :total_amount, :delivery_address, 'pending')";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":user_id", $data->user_id);
        $stmt->bindParam(":total_amount", $data->total_amount);
        $stmt->bindParam(":delivery_address", $data->delivery_address);
        $stmt->execute();
        
        $order_id = $conn->lastInsertId();

        // 2. Insert Items
        $query_item = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (:order_id, :product_id, :quantity, :unit_price)";
        $stmt_item = $conn->prepare($query_item);

        foreach($data->items as $item){
            $stmt_item->bindParam(":order_id", $order_id);
            $stmt_item->bindParam(":product_id", $item->product_id);
            $stmt_item->bindParam(":quantity", $item->quantity);
            $stmt_item->bindParam(":unit_price", $item->unit_price);
            $stmt_item->execute();
        }

        // 3. Create Payment Record (Pending)
        $payment_method = isset($data->payment_method) ? $data->payment_method : 'cash_on_delivery';
        $query_pay = "INSERT INTO payments (order_id, payment_method, payment_status) VALUES (:order_id, :payment_method, 'pending')";
        $stmt_pay = $conn->prepare($query_pay);
        $stmt_pay->bindParam(":order_id", $order_id);
        $stmt_pay->bindParam(":payment_method", $payment_method);
        $stmt_pay->execute();

        $conn->commit();
        
        http_response_code(201);
        echo json_encode(["message" => "Order placed successfully.", "order_id" => $order_id]);

    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(503);
        echo json_encode(["message" => "Order failed: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete order data."]);
}
?>
