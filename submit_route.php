<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: dashboard.php");
    exit();
}

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid request');
    }

    // Validate and sanitize input
    $start_time = filter_var($_POST['start_time'], FILTER_SANITIZE_STRING);
    $end_time = filter_var($_POST['end_time'], FILTER_SANITIZE_STRING);
    $total_stops = filter_var($_POST['total_stops'], FILTER_VALIDATE_INT);
    $dumps = filter_var($_POST['dumps'], FILTER_VALIDATE_INT);
    $total_weight = filter_var($_POST['total_weight'], FILTER_VALIDATE_FLOAT);

    // Validation checks
    if (!$start_time || !$end_time) {
        throw new Exception("Invalid time values");
    }

    if ($total_stops === false || $total_stops < 1) {
        throw new Exception("Total stops must be at least 1");
    }

    if ($dumps === false || $dumps < 1) {
        throw new Exception("Number of dumps must be at least 1");
    }

    if ($total_weight === false || $total_weight <= 0) {
        throw new Exception("Total weight must be greater than 0");
    }

    // Validate time range
    $start = strtotime($start_time);
    $end = strtotime($end_time);

    if ($start >= $end) {
        throw new Exception("End time must be after start time");
    }

    // Check for duplicate submissions
    $sql = "SELECT id FROM routes 
            WHERE driver_id = ? 
            AND route_date = CURRENT_DATE 
            AND start_time = ? 
            AND end_time = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to check for duplicates: " . $conn->error);
    }

    $stmt->bind_param("iss", $_SESSION['user_id'], $start_time, $end_time);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("This route has already been submitted");
    }

    // Calculate score
    $route_data = [
        'start_time' => $start_time,
        'end_time' => $end_time,
        'total_stops' => $total_stops,
        'dumps' => $dumps,
        'total_weight' => $total_weight
    ];
    $score = calculateScore($route_data);

    // Begin transaction
    $conn->begin_transaction();

    // Insert route
    $sql = "INSERT INTO routes (
                driver_id, 
                route_date, 
                start_time, 
                end_time, 
                total_stops, 
                dumps, 
                total_weight, 
                score,
                created_at
            ) VALUES (?, CURRENT_DATE(), ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare route insertion: " . $conn->error);
    }

    $stmt->bind_param("issiidd", 
        $_SESSION['user_id'], 
        $start_time, 
        $end_time, 
        $total_stops, 
        $dumps, 
        $total_weight, 
        $score
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to submit route: " . $stmt->error);
    }

    // Check for new achievements
    $new_achievements = checkAchievements($conn, $_SESSION['user_id']);

    // Commit transaction
    $conn->commit();

    // Set success message
    if (!empty($new_achievements)) {
        $achievementNames = array_map(function($a) { 
            return htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8'); 
        }, $new_achievements);
        $_SESSION['success'] = "Route submitted successfully! You earned new achievements: " . 
                              implode(", ", $achievementNames);
    } else {
        $_SESSION['success'] = "Route submitted successfully!";
    }

    header("Location: dashboard.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction in case of an error
    $conn->rollback();

    error_log("Route submission error: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
    header("Location: dashboard.php");
    exit();
}
?>
