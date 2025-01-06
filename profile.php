<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require_once 'config.php';
require_once 'functions.php';

// Initialize variables
$driver = null;
$recent_routes = [];
$rank_info = [
    'rank' => '-',
    'avg_score' => 0,
    'total_routes' => 0
];
$achievements = [];
$earned_count = 0;
$total_achievements = 0;
$error = null;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    // Get driver details and statistics with null coalescing
    $sql = "SELECT d.*, 
            COUNT(r.id) as total_routes,
            COALESCE(ROUND(AVG(r.score), 1), 0) as avg_score,
            COALESCE(MAX(r.score), 0) as highest_score,
            COALESCE(SUM(r.total_stops), 0) as total_stops,
            COALESCE(SUM(r.total_weight), 0) as total_weight,
            COALESCE(SUM(r.dumps), 0) as total_dumps
            FROM drivers d
            LEFT JOIN routes r ON d.id = r.driver_id
            WHERE d.id = ?
            GROUP BY d.id";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $driver = $result->fetch_assoc();

    if (!$driver) {
        throw new Exception("Driver not found");
    }

    // Get recent routes
    $sql = "SELECT * FROM routes 
            WHERE driver_id = ? 
            ORDER BY route_date DESC, start_time DESC 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Recent routes query failed: " . $conn->error);
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $recent_routes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get driver's rank
    $rank_info = getDriverRank($conn, $_SESSION['user_id']);

    // Get achievements
    $sql = "SELECT a.*, da.earned_at 
            FROM achievements a
            LEFT JOIN driver_achievements da 
                ON a.id = da.achievement_id 
                AND da.driver_id = ?
            ORDER BY 
                CASE WHEN da.earned_at IS NOT NULL THEN 0 ELSE 1 END,
                da.earned_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Achievements query failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get achievement counts
    $sql = "SELECT COUNT(*) as earned FROM driver_achievements WHERE driver_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $earned_count = $stmt->get_result()->fetch_assoc()['earned'] ?? 0;

    $result = $conn->query("SELECT COUNT(*) as total FROM achievements");
    $total_achievements = $result ? ($result->fetch_assoc()['total'] ?? 0) : 0;

} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error = "An error occurred loading your profile. Please try again later.";
}

// Set default values if data is missing
$driver = $driver ?? [
    'name' => 'Unknown Driver',
    'created_at' => date('Y-m-d H:i:s'),
    'total_routes' => 0,
    'highest_score' => 0,
    'total_stops' => 0,
    'total_weight' => 0
];

include 'header.php';
?>

<div class="max-w-4xl mx-auto">
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo h($error); ?>
        </div>
    <?php endif; ?>

    <!-- Profile Overview -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold mb-2"><?php echo h($driver['name']); ?></h2>
                    <p class="text-gray-600">Member since: <?php echo formatDate($driver['created_at']); ?></p>
                </div>
                <a href="edit_profile.php" 
                   class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                    Edit Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-gray-500 text-sm mb-1">Overall Rank</h3>
            <p class="text-2xl font-bold text-blue-600">
                #<?php echo h($rank_info['rank']); ?>
            </p>
            <p class="text-sm text-gray-500">
                Average Score: <?php echo number_format($rank_info['avg_score'] ?? 0, 1); ?>
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-gray-500 text-sm mb-1">Highest Score</h3>
            <p class="text-2xl font-bold text-green-600">
                <?php echo number_format($driver['highest_score'] ?? 0, 1); ?>
            </p>
            <p class="text-sm text-gray-500">
                Total Routes: <?php echo number_format($driver['total_routes'] ?? 0); ?>
            </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-gray-500 text-sm mb-1">Total Collection</h3>
            <p class="text-2xl font-bold">
                <?php echo number_format($driver['total_weight'] ?? 0, 1); ?> tons
            </p>
            <p class="text-sm text-gray-500">
                <?php echo number_format($driver['total_stops'] ?? 0); ?> stops completed
            </p>
        </div>
    </div>

    <!-- Achievements Section -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-bold">Achievements</h2>
            <span class="text-sm text-gray-600">
                <?php echo $earned_count; ?> / <?php echo $total_achievements; ?> Earned
            </span>
        </div>
        <div class="p-4">
            <?php if (empty($achievements)): ?>
                <p class="text-gray-500 text-center py-4">No achievements available.</p>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($achievements as $achievement): ?>
                    <div class="border rounded-lg p-4 <?php echo isset($achievement['earned_at']) ? 'bg-blue-50' : 'opacity-60'; ?>">
                        <div class="flex items-start space-x-3">
                            <div class="text-2xl">
                                <?php echo h($achievement['icon'] ?? '🏆'); ?>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold">
                                    <?php echo h($achievement['name']); ?>
                                </h3>
                                <p class="text-sm text-gray-600">
                                    <?php echo h($achievement['description']); ?>
                                </p>
                                <?php if (isset($achievement['earned_at'])): ?>
                                    <p class="text-xs text-green-600 mt-1">
                                        Earned <?php echo formatDate($achievement['earned_at']); ?>
                                    </p>
                                <?php else: ?>
                                    <?php
                                    // Calculate progress
                                    $progress = 0;
                                    $stat = '';
                                    $requirement_value = $achievement['requirement_value'] ?? 0;
                                    
                                    if ($requirement_value > 0) {
                                        switch ($achievement['requirement_type'] ?? '') {
                                            case 'total_routes':
                                                $progress = min(100, (($driver['total_routes'] ?? 0) / $requirement_value) * 100);
                                                $stat = number_format($driver['total_routes'] ?? 0) . ' routes';
                                                break;
                                            case 'total_stops':
                                                $progress = min(100, (($driver['total_stops'] ?? 0) / $requirement_value) * 100);
                                                $stat = number_format($driver['total_stops'] ?? 0) . ' stops';
                                                break;
                                            case 'total_weight':
                                                $progress = min(100, (($driver['total_weight'] ?? 0) / $requirement_value) * 100);
                                                $stat = number_format($driver['total_weight'] ?? 0, 1) . ' tons';
                                                break;
                                            default:
                                                $stat = 'In Progress';
                                        }
                                    }
                                    ?>
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                                            <span>Progress</span>
                                            <span><?php echo $stat; ?></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" 
                                                style="width: <?php echo $progress; ?>%">
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Routes -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-4 border-b">
            <h2 class="text-lg font-bold">Recent Routes</h2>
        </div>
        <div class="p-4">
            <?php if (empty($recent_routes)): ?>
                <p class="text-gray-500 text-center py-4">No routes logged yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-left">Time</th>
                                <th class="px-4 py-2 text-center">Stops</th>
                                <th class="px-4 py-2 text-center">Dumps</th>
                                <th class="px-4 py-2 text-center">Weight</th>
                                <th class="px-4 py-2 text-center">Score</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($recent_routes as $route): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <?php echo htmlspecialchars(formatDate($route['route_date'])); ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php echo htmlspecialchars(formatTime($route['start_time'])); ?> - 
                                        <?php echo htmlspecialchars(formatTime($route['end_time'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-center"><?php echo (int)($route['total_stops'] ?? 0); ?></td>
                                    <td class="px-4 py-3 text-center"><?php echo (int)($route['dumps'] ?? 0); ?></td>
                                    <td class="px-4 py-3 text-center"><?php echo number_format($route['total_weight'] ?? 0, 1); ?></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-bold <?php echo ($route['score'] ?? 0) >= 90 ? 'text-green-600' : ''; ?>">
                                            <?php echo number_format($route['score'] ?? 0, 1); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>