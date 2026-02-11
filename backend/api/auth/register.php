<?php
// backend/api/auth/register.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

// Get raw POST data
$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->full_name) &&
    !empty($data->email) &&
    !empty($data->password)
){
    // Sanitize
    $full_name = htmlspecialchars(strip_tags($data->full_name));
    $email = htmlspecialchars(strip_tags($data->email));
    $phone = isset($data->phone_number) ? htmlspecialchars(strip_tags($data->phone_number)) : '';
    $password = $data->password;

    // Check if email exists
    $check_query = "SELECT user_id FROM users WHERE email = :email LIMIT 1";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();

    if($check_stmt->rowCount() > 0){
        http_response_code(400); // Bad Request
        echo json_encode(["message" => "Email already exists."]);
        exit();
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert new user
    // Determine role (for simplicity in this demo, strict customer unless manual db override or special logic)
    $role = 'customer'; 

    $query = "INSERT INTO users (full_name, email, password_hash, phone_number, role) VALUES (:full_name, :email, :password_hash, :phone, :role)";
    $stmt = $conn->prepare($query);

    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password_hash', $password_hash);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':role', $role);

    if($stmt->execute()){
        http_response_code(201); // Created
        echo json_encode(["message" => "User registered successfully."]);
    } else {
        http_response_code(503); // Service Unavailable
        echo json_encode(["message" => "Unable to register user."]);
    }
} else {
    http_response_code(400); // Bad Request
    echo json_encode(["message" => "Incomplete data."]);
}
?>
