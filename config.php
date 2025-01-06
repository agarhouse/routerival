<?php
// Database connection settings
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'routerival');

// Create connection with error handling
try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set UTF-8 character set
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error loading character set utf8mb4: " . $conn->error);
    }
    
    // Set timezone for database operations
    if (!$conn->query("SET time_zone = '+00:00'")) {
        throw new Exception("Error setting time zone: " . $conn->error);
    }
    
} catch (Exception $e) {
    // Log error and show generic message
    error_log($e->getMessage());
    die("A database error occurred. Please try again later.");
}
?>