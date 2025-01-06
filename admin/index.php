<?php
require_once 'check_auth.php';
require_once '../config.php';
require_once '../functions.php';

// Get system stats
$stats = getSystemStats($conn);

// Get recent routes
$sql = "SELECT r.*, d.name as driver_name 
        FROM routes r 
        JOIN drivers d ON r.driver_id = d.id 
        ORDER BY r.created_at DESC 
        LIMIT 10";
$recent_routes = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Get all drivers
$sql = "SELECT * FROM drivers ORDER BY name";
$drivers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Route Rival</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Admin Header -->
        <header class="bg-gray-800 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">Admin Dashboard</h1>
                <nav class="space-x-4">
                    <a href="index.php" class="hover:text-gray-300">Dashboard</a>
                    <a href="manage_drivers.php" class="hover:text-gray-300">Manage Drivers</a>
                    <a href="logout.php" class="hover:text-gray-300">Logout</a>
                </nav>
            </div>
        </header>

        <main class="container mx-auto p-4">
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Total Drivers</h3>
                    <p class="text-2xl font-bold"><?php echo $stats['total_drivers']; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Total Routes</h3>
                    <p class="text-2xl font-bold"><?php echo $stats['total_routes']; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Total Stops</h3>
                    <p class="text-2xl font-bold"><?php echo formatNumber($stats['total_stops']); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-gray-500 text-sm">Total Weight (tons)</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($stats['total_weight'], 1); ?></p>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Recent Routes -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b">
                        <h2 class="text-lg font-bold">Recent Routes</h2>
                    </div>
                    <div class="p-4">
                        <div class="space-y-4">
                            <?php foreach ($recent_routes as $route): ?>
                                <div class="border-b pb-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-bold"><?php echo h($route['driver_name']); ?></h4>
                                            <p class="text-sm text-gray-600">
                                                <?php echo $route['total_stops']; ?> stops · 
                                                <?php echo $route['total_weight']; ?> tons
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                <?php echo formatDate($route['route_date']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-blue-600">
                                                <?php echo number_format($route['score'], 1); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-4 border-b">
                        <h2 class="text-lg font-bold">Quick Actions</h2>
                    </div>
                    <div class="p-4">
                        <div class="space-y-4">
                            <a href="manage_drivers.php" 
                               class="block w-full p-4 bg-blue-50 rounded border border-blue-200 hover:bg-blue-100">
                                <h3 class="font-bold text-blue-700">Manage Drivers</h3>
                                <p class="text-sm text-blue-600">Add, edit, or deactivate drivers</p>
                            </a>

                            <a href="manage_achievements.php" 
                               class="block w-full p-4 bg-yellow-50 rounded border border-yellow-200 hover:bg-yellow-100">
                                <h3 class="font-bold text-yellow-700">Manage Achievements</h3>
                                <p class="text-sm text-yellow-600">manage all achievements for a drivers</p>
                            
                            <a href="export_data.php" 
                               class="block w-full p-4 bg-green-50 rounded border border-green-200 hover:bg-green-100">
                                <h3 class="font-bold text-green-700">Export Data</h3>
                                <p class="text-sm text-green-600">Download route and driver data</p>
                            </a>
                            
                            <a href="settings.php" 
                               class="block w-full p-4 bg-purple-50 rounded border border-purple-200 hover:bg-purple-100">
                                <h3 class="font-bold text-purple-700">System Settings</h3>
                                <p class="text-sm text-purple-600">Configure scoring and system parameters</p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>