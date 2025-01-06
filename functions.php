<?php
/**
 * functions.php - Core functions for Route Tracker application
 */

/**
 * Calculate driver's score based on route performance
 */
function calculateScore($route) {
    if (!isset($route['start_time']) || !isset($route['end_time']) || 
        !isset($route['total_stops']) || !isset($route['dumps']) || 
        !isset($route['total_weight'])) {
        return 0;
    }

    // Calculate hours worked
    $start = strtotime($route['start_time']);
    $end = strtotime($route['end_time']);
    $hours_worked = max(($end - $start) / 3600, 0.5); // Minimum 30 minutes
    
    // Calculate metrics
    $stops_per_hour = $route['total_stops'] / $hours_worked;
    $weight_per_dump = $route['total_weight'] / max($route['dumps'], 1); // Prevent division by zero
    
    // Weight each component
    $efficiency_score = ($stops_per_hour * 5) + ($weight_per_dump * 3);
    
    // Cap the score at 100 and round to 1 decimal
    return round(min(100, $efficiency_score), 1);
}

/**
 * Get leaderboard data based on time period
 */
function getLeaderboard($conn, $date, $period = 'today') {
    try {
        switch ($period) {
            case 'week':
                $sql = "SELECT 
                        d.id as driver_id,
                        d.name,
                        COUNT(r.id) as total_routes,
                        ROUND(AVG(r.score), 1) as score,
                        SUM(r.total_stops) as total_stops,
                        SUM(r.dumps) as dumps,
                        SUM(r.total_weight) as total_weight,
                        MIN(r.start_time) as start_time,
                        MAX(r.end_time) as end_time
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
                        COUNT(r.id) as total_routes,
                        ROUND(AVG(r.score), 1) as score,
                        SUM(r.total_stops) as total_stops,
                        SUM(r.dumps) as dumps,
                        SUM(r.total_weight) as total_weight,
                        MIN(r.start_time) as start_time,
                        MAX(r.end_time) as end_time
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
                $stmt->bind_param("s", $date);
                break;
        }

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        error_log("Leaderboard error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get driver's personal stats
 */
function getDriverStats($conn, $driver_id) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_routes,
                    ROUND(AVG(score), 1) as avg_score,
                    MAX(score) as best_score,
                    SUM(total_stops) as total_stops,
                    SUM(dumps) as total_dumps,
                    SUM(total_weight) as total_weight
                FROM routes 
                WHERE driver_id = ?";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result ?: [
            'total_routes' => 0,
            'avg_score' => 0,
            'best_score' => 0,
            'total_stops' => 0,
            'total_dumps' => 0,
            'total_weight' => 0
        ];
    } catch (Exception $e) {
        error_log("Driver stats error: " . $e->getMessage());
        return [
            'total_routes' => 0,
            'avg_score' => 0,
            'best_score' => 0,
            'total_stops' => 0,
            'total_dumps' => 0,
            'total_weight' => 0
        ];
    }
}

/**
 * Format time to 12-hour format
 */
function formatTime($time) {
    if (empty($time)) return '';
    try {
        return date("g:i A", strtotime($time));
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Calculate route duration in hours and minutes
 */
function calculateDuration($start_time, $end_time) {
    if (empty($start_time) || empty($end_time)) return '0h 0m';
    try {
        $start = strtotime($start_time);
        $end = strtotime($end_time);
        $duration = max(0, $end - $start);
        
        $hours = floor($duration / 3600);
        $minutes = floor(($duration % 3600) / 60);
        
        return sprintf("%dh %dm", $hours, $minutes);
    } catch (Exception $e) {
        return '0h 0m';
    }
}

/**
 * Get driver's rank info
 */
function getDriverRank($conn, $driver_id) {
    try {
        // First try with ROW_NUMBER
        $sql = "SELECT 
                ranked.rank as `rank`,
                ranked.avg_score,
                ranked.total_routes
            FROM (
                SELECT 
                    driver_id,
                    ROUND(AVG(score), 1) as avg_score,
                    COUNT(*) as total_routes,
                    RANK() OVER (ORDER BY AVG(score) DESC) as `rank`
                FROM routes 
                GROUP BY driver_id
            ) ranked
            WHERE driver_id = ?";
            
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            // Fallback for older MySQL versions
            $sql = "SELECT 
                    stats.*,
                    (
                        SELECT COUNT(DISTINCT driver_id) + 1
                        FROM routes r2
                        GROUP BY r2.driver_id
                        HAVING AVG(r2.score) > stats.avg_score
                    ) as `rank`
                FROM (
                    SELECT 
                        ROUND(AVG(score), 1) as avg_score,
                        COUNT(*) as total_routes
                    FROM routes
                    WHERE driver_id = ?
                    GROUP BY driver_id
                ) stats";
                
            $stmt = $conn->prepare($sql);
        }

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result) {
            return [
                'rank' => '-',
                'avg_score' => 0,
                'total_routes' => 0
            ];
        }

        return [
            'rank' => (int)$result['rank'],
            'avg_score' => (float)$result['avg_score'],
            'total_routes' => (int)$result['total_routes']
        ];
    } catch (Exception $e) {
        error_log("Rank calculation error: " . $e->getMessage());
        return [
            'rank' => '-',
            'avg_score' => 0,
            'total_routes' => 0
        ];
    }
}

/**
 * Check and award achievements
 */
function checkAchievements($conn, $driver_id) {
    try {
        $new_achievements = [];
        
        // Get driver's stats
        $sql = "SELECT 
                COUNT(*) as total_routes,
                SUM(total_stops) as total_stops,
                SUM(total_weight) as total_weight,
                MAX(score) as highest_score,
                COUNT(CASE WHEN TIME(start_time) < '06:00:00' THEN 1 END) as early_starts
                FROM routes 
                WHERE driver_id = ?";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare stats query");
        }
        
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();

        // Get earned achievements
        $sql = "SELECT achievement_id FROM driver_achievements WHERE driver_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare achievements query");
        }
        
        $stmt->bind_param("i", $driver_id);
        $stmt->execute();
        $earned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $earned_ids = array_column($earned, 'achievement_id');

        // Get all achievements
        $achievements = $conn->query("SELECT * FROM achievements");
        if (!$achievements) {
            throw new Exception("Failed to fetch achievements");
        }

        while ($achievement = $achievements->fetch_assoc()) {
            if (in_array($achievement['id'], $earned_ids)) {
                continue;
            }

            $earned = false;
            
            switch ($achievement['requirement_type']) {
                case 'total_routes':
                    $earned = $stats['total_routes'] >= $achievement['requirement_value'];
                    break;
                    
                case 'total_stops':
                    $earned = $stats['total_stops'] >= $achievement['requirement_value'];
                    break;
                    
                case 'total_weight':
                    $earned = $stats['total_weight'] >= $achievement['requirement_value'];
                    break;
                    
                case 'high_score':
                    $earned = $stats['highest_score'] >= $achievement['requirement_value'];
                    break;
                    
                case 'early_starts':
                    $earned = $stats['early_starts'] >= $achievement['requirement_value'];
                    break;
                    
                case 'streak_score':
                    $sql = "SELECT COUNT(*) as streak FROM (
                            SELECT score >= ? as high_score
                            FROM routes
                            WHERE driver_id = ?
                            ORDER BY route_date DESC, start_time DESC
                            LIMIT ?
                        ) scores
                        WHERE high_score = 1";
                    
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $threshold = 90;
                        $limit = $achievement['requirement_value'];
                        $stmt->bind_param("dii", $threshold, $driver_id, $limit);
                        $stmt->execute();
                        $result = $stmt->get_result()->fetch_assoc();
                        $earned = $result['streak'] >= $achievement['requirement_value'];
                    }
                    break;
            }

            if ($earned) {
                $sql = "INSERT INTO driver_achievements (driver_id, achievement_id, earned_at) 
                        VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("ii", $driver_id, $achievement['id']);
                    if ($stmt->execute()) {
                        $new_achievements[] = $achievement;
                    }
                }
            }
        }

        return $new_achievements;
    } catch (Exception $e) {
        error_log("Achievement check error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get system stats
 */
function getSystemStats($conn) {
    try {
        $sql = "SELECT 
                (SELECT COUNT(*) FROM drivers) as total_drivers,
                COUNT(DISTINCT driver_id) as active_drivers,
                COUNT(*) as total_routes,
                COALESCE(SUM(total_stops), 0) as total_stops,
                COALESCE(SUM(total_weight), 0) as total_weight,
                COALESCE(AVG(score), 0) as avg_score
                FROM routes";
                
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $stats = $result->fetch_assoc();
        
        return [
            'total_drivers' => (int)($stats['total_drivers'] ?? 0),
            'active_drivers' => (int)($stats['active_drivers'] ?? 0),
            'total_routes' => (int)($stats['total_routes'] ?? 0),
            'total_stops' => (int)($stats['total_stops'] ?? 0),
            'total_weight' => round(floatval($stats['total_weight'] ?? 0), 1),
            'avg_score' => round(floatval($stats['avg_score'] ?? 0), 1)
        ];
    } catch (Exception $e) {
        error_log("System stats error: " . $e->getMessage());
        return [
            'total_drivers' => 0,
            'active_drivers' => 0,
            'total_routes' => 0,
            'total_stops' => 0,
            'total_weight' => 0,
            'avg_score' => 0
        ];
    }
}

/**
 * Format number with K/M suffix
 */
function formatNumber($number) {
    if (!is_numeric($number)) return '0';
    
    try {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return number_format($number);
    } catch (Exception $e) {
        return '0';
    }
}

/**
 * Format date with proper error handling
 */
function formatDate($date) {
    if (empty($date)) return '';
    try {
        return date("M j, Y", strtotime($date));
    } catch (Exception $e) {
        error_log("Date formatting error: " . $e->getMessage());
        return '';
    }
}

/**
 * Safe HTML escaping
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Format datetime for MySQL
 */
function formatDateTime($date) {
    if (empty($date)) return NULL;
    try {
        return date("Y-m-d H:i:s", strtotime($date));
    } catch (Exception $e) {
        error_log("DateTime formatting error: " . $e->getMessage());
        return NULL;
    }
}
?>