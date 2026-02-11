<?php
// backend/api/auth/login.php
require_once '../../config/cors.php';
require_once '../../config/db.php';

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->email) && !empty($data->password)){
    $email = htmlspecialchars(strip_tags($data->email));
    
    $query = "SELECT user_id, full_name, role, password_hash FROM users WHERE email = :email LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if($stmt->rowCount() > 0){
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $password_hash = $row['password_hash'];
        
        if(password_verify($data->password, $password_hash)){
            // Password Correct
            // For a production app, use JWT. For this scope, we return user info to store in LocalStorage.
            // Ideally, set a session or token.
            
            $user_data = [
                'user_id' => $row['user_id'],
                'full_name' => $row['full_name'],
                'email' => $email,
                'role' => $row['role']
            ];
            
            http_response_code(200);
            echo json_encode([
                "message" => "Login successful.",
                "user" => $user_data
                // "token" => generate_jwt(...) // Optional: If we were implementing JWT
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid password."]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Email not found."]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete login data."]);
}
?>
