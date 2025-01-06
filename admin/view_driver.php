<?php
require_once 'check_auth.php';
require_once '../config.php';
require_once '../functions.php';

// Get driver ID from URL
$driver_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$driver_id) {
    header("Location: manage_drivers.php");
    exit();
}

// Get driver details
$sql = "SELECT d.*, 
        COUNT(r.id) as total_routes,
        AVG(r.score) as avg_score,
        MAX(r.score) as highest_score,
        MIN(r.score) as lowest_score,
        SUM(r.total_stops) as total_stops,
        SUM(r.total_weight) as total_weight
        FROM drivers d
        LEFT JOIN routes r ON d.id = r.driver_id
        WHERE d.id = ?
        GROUP BY d.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();

if (!$driver) {
    header("Location: manage_drivers.php");
    exit();
}

// Get recent routes
$sql = "SELECT * FROM routes 
        WHERE driver_id = ? 
        ORDER BY route_date DESC, start_time DESC 
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$recent_routes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Driver - <?php echo h($driver['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Admin Header -->
        <header class="bg-gray-800 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">Driver Details</h1>
                <nav class="space-x-4">
                    <a href="index.php" class="hover:text-gray-300">Dashboard</a>
                    <a href="manage_drivers.php" class="hover:text-gray-300">Manage Drivers</a>
                    <a href="logout.php" class="hover:text-gray-300">Logout</a>
                </nav>
            </div>
        </header>

        <main class="container mx-auto p-4">
            <!-- Driver Overview -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h2 class="text-2xl font-bold mb-2"><?php echo h($driver['name']); ?></h2>
                            <p class="text-gray-600">Username: <?php echo h($driver['username']); ?></p>
                            <p class="text-gray-600">Member since: <?php echo date('M j, Y', strtotime($driver['created_at'])); ?></p>
                        </div>
                        <button onclick="showResetPassword(<?php echo $driver['id']; ?>)"
                            class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">
                            Reset Password
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Total Routes</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($driver['total_routes']); ?></p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Average Score</h3>
                    <p class="text-2xl font-bold">
                        <?php echo $driver['avg_score'] ? number_format($driver['avg_score'], 1) : '-'; ?>
                    </p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Total Stops</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($driver['total_stops']); ?></p>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Total Weight (tons)</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($driver['total_weight'], 1); ?></p>
                </div>
            </div>

            <!-- Recent Routes -->
            <div class="bg-white rounded-lg shadow">
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
                                                <?php echo date('M j, Y', strtotime($route['route_date'])); ?>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php echo formatTime($route['start_time']); ?> - 
                                                <?php echo formatTime($route['end_time']); ?>
                                            </td>
                                            <td class="px-4 py-3 text-center"><?php echo $route['total_stops']; ?></td>
                                            <td class="px-4 py-3 text-center"><?php echo $route['dumps']; ?></td>
                                            <td class="px-4 py-3 text-center"><?php echo $route['total_weight']; ?></td>
                                            <td class="px-4 py-3 text-center font-bold">
                                                <?php echo number_format($route['score'], 1); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <h3 class="text-lg font-bold mb-4">Reset Password</h3>
            <form method="POST" action="manage_drivers.php" class="space-y-4">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="driver_id" id="resetDriverId">
                
                <div>
                    <label class="block text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" required 
                        class="w-full p-2 border rounded">
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="button" onclick="hideResetPassword()"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showResetPassword(driverId) {
            document.getElementById('resetDriverId').value = driverId;
            document.getElementById('resetPasswordModal').classList.remove('hidden');
        }

        function hideResetPassword() {
            document.getElementById('resetPasswordModal').classList.add('hidden');
        }
    </script>
</body>
</html>