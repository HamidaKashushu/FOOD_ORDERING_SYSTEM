<?php
// backend/config/db.php

$host = 'localhost';
$db_name = 'd_food';
$username = 'root'; // Default XAMPP username
$password = '';     // Default XAMPP password usually empty

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Return a JSON error if connection fails, useful for API handling
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Connection Error: ' . $e->getMessage()]);
    exit();
}
?>
