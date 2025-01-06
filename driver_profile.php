<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Get driver ID from URL with validation
$driver_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$driver_id) {
    header("Location: index.php");
    exit();
}

try {
    // Get driver details with all necessary statistics
    // Only show public profiles
    $sql = "SELECT d.*, 
            COUNT(r.id) as total_routes,
            COALESCE(ROUND(AVG(r.score), 1), 0) as avg_score,
            MAX(r.score) as highest_score,
            SUM(r.total_stops) as total_stops,
            SUM(r.total_weight) as total_weight
            FROM drivers d
            LEFT JOIN routes r ON d.id = r.driver_id
            WHERE d.id = ? AND d.public_profile = 1
            GROUP BY d.id";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();

    if (!$driver) {
        throw new Exception("Driver not found or profile is private");
    }

    // Get driver's rank info
    $rank_info = getDriverRank($conn, $driver_id);

    // Get driver's achievements
    $sql = "SELECT a.*, da.earned_at 
            FROM achievements a
            INNER JOIN driver_achievements da ON a.id = da.achievement_id 
            WHERE da.driver_id = ?
            ORDER BY da.earned_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Achievement query failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get recent routes
    $sql = "SELECT * FROM routes 
            WHERE driver_id = ? 
            ORDER BY route_date DESC, start_time DESC 
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Recent routes query failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $recent_routes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    error_log("Driver profile error: " . $e->getMessage());
    $_SESSION['error'] = "Profile not found or is set to private.";
    header("Location: index.php");
    exit();
}

// Set page title
$page_title = "Driver Profile - " . h($driver['name']);
include 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <!-- Driver Overview -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold mb-2"><?php echo h($driver['name']); ?></h2>
                    <p class="text-gray-600">
                        <?php if ($driver['company']): ?>
                            <?php echo h($driver['company']); ?> · 
                        <?php endif; ?>
                        <?php if ($driver['line_of_business']): ?>
                            <?php echo h($driver['line_of_business']); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($driver['years_experience']): ?>
                        <p class="text-gray-600">
                            <?php echo (int)$driver['years_experience']; ?> years experience
                        </p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500 mt-2">
                        Member since <?php echo formatDate($driver['created_at']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">
                        #<?php echo h($rank_info['rank']); ?>
                    </div>
                    <div class="text-sm text-gray-500">Current Rank</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-gray-500 text-sm mb-1">Average Score</h3>
            <p class="text-2xl font-bold">
                <?php echo number_format($driver['avg_score'], 1); ?>
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-gray-500 text-sm mb-1">Total Routes</h3>
            <p class="text-2xl font-bold">
                <?php echo number_format($driver['total_routes']); ?>
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-gray-500 text-sm mb-1">Total Stops</h3>
            <p class="text-2xl font-bold">
                <?php echo number_format($driver['total_stops']); ?>
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-gray-500 text-sm mb-1">Waste Collected</h3>
            <p class="text-2xl font-bold">
                <?php echo number_format($driver['total_weight'], 1); ?> tons
            </p>
        </div>
    </div>

    <!-- Achievements -->
    <?php if (!empty($achievements)): ?>
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-4 border-b">
            <h2 class="text-lg font-bold">Achievements</h2>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($achievements as $achievement): ?>
                    <div class="border rounded-lg p-4 bg-blue-50">
                        <div class="flex items-start space-x-3">
                            <div class="text-2xl">
                                <?php echo h($achievement['icon']); ?>
                            </div>
                            <div>
                                <h3 class="font-bold">
                                    <?php echo h($achievement['name']); ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    <?php echo h($achievement['description']); ?>
                                </p>
                                <p class="text-xs text-green-600 mt-1">
                                    Earned <?php echo formatDate($achievement['earned_at']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Routes -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-4 border-b">
            <h2 class="text-lg font-bold">Recent Routes</h2>
        </div>
        <div class="p-4">
            <?php if (empty($recent_routes)): ?>
                <p class="text-gray-500 text-center py-4">No routes logged yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recent_routes as $route): ?>
                        <div class="border rounded p-4 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm text-gray-600">
                                        <?php echo (int)$route['total_stops']; ?> stops · 
                                        <?php echo (int)$route['dumps']; ?> dumps · 
                                        <?php echo number_format($route['total_weight'], 1); ?> tons
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo formatDate($route['route_date']); ?> ·
                                        <?php echo formatTime($route['start_time']); ?> - 
                                        <?php echo formatTime($route['end_time']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-xl font-bold text-blue-600">
                                        <?php echo number_format($route['score'], 1); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">Score</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-6 text-center">
        <a href="index.php" class="text-blue-600 hover:underline">
            Back to Leaderboard
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>