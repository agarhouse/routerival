<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

try {
    // Validate period
    $allowed_periods = ['today', 'week', 'month'];
    $period = isset($_GET['period']) && in_array($_GET['period'], $allowed_periods) ? $_GET['period'] : 'today';
    $today = date('Y-m-d');

    switch ($period) {
        case 'week':
            $sql = "SELECT 
                    d.id as driver_id,
                    d.name,
                    COUNT(*) as total_routes,
                    ROUND(AVG(r.score), 1) as score,
                    SUM(r.total_stops) as total_stops,
                    SUM(r.dumps) as dumps,
                    SUM(r.total_weight) as total_weight
                FROM routes r 
                JOIN drivers d ON r.driver_id = d.id 
                WHERE r.route_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
                    AND d.public_profile = 1
                GROUP BY d.id, d.name
                ORDER BY score DESC, total_routes DESC
                LIMIT 20";
            $stmt = $conn->prepare($sql);
            break;

        case 'month':
            $sql = "SELECT 
                    d.id as driver_id,
                    d.name,
                    COUNT(*) as total_routes,
                    ROUND(AVG(r.score), 1) as score,
                    SUM(r.total_stops) as total_stops,
                    SUM(r.dumps) as dumps,
                    SUM(r.total_weight) as total_weight
                FROM routes r 
                JOIN drivers d ON r.driver_id = d.id 
                WHERE DATE_FORMAT(r.route_date, '%Y-%m') = DATE_FORMAT(CURRENT_DATE, '%Y-%m')
                    AND d.public_profile = 1
                GROUP BY d.id, d.name
                ORDER BY score DESC, total_routes DESC
                LIMIT 20";
            $stmt = $conn->prepare($sql);
            break;

        default: // today
            $sql = "SELECT 
                    r.*, 
                    d.name,
                    d.id as driver_id
                FROM routes r 
                JOIN drivers d ON r.driver_id = d.id 
                WHERE DATE(r.route_date) = ?
                    AND d.public_profile = 1
                ORDER BY r.score DESC, r.total_stops DESC
                LIMIT 20";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception($conn->error);
            }
            $stmt->bind_param("s", $today);
            break;
    }

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception($stmt->error);
    }

    $leaderboard = $result->fetch_all(MYSQLI_ASSOC);

    // Format the data
    foreach ($leaderboard as &$entry) {
        // Ensure all numeric fields are properly formatted
        $entry['total_stops'] = (int)($entry['total_stops'] ?? 0);
        $entry['dumps'] = (int)($entry['dumps'] ?? 0);
        $entry['total_weight'] = number_format((float)($entry['total_weight'] ?? 0), 1);
        $entry['score'] = number_format((float)($entry['score'] ?? 0), 1);

        // Format times if they exist
        if (isset($entry['start_time'])) {
            $entry['start_time'] = date('g:i A', strtotime($entry['start_time']));
        }
        if (isset($entry['end_time'])) {
            $entry['end_time'] = date('g:i A', strtotime($entry['end_time']));
        }

        // Remove sensitive fields
        unset(
            $entry['password_hash'],
            $entry['created_at'],
            $entry['updated_at'],
            $entry['last_login']
        );
    }

    echo json_encode([
        'success' => true,
        'data' => $leaderboard,
        'period' => $period,
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    error_log("Leaderboard error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching the leaderboard',
        'debug' => $e->getMessage() // Remove this in production
    ]);
}
?>