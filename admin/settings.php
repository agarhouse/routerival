<?php
require_once 'check_auth.php';
require_once '../config.php';
require_once '../functions.php';

$message = '';
$error = '';

// Create settings table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE,
    setting_value TEXT,
    description TEXT,
    type VARCHAR(20),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Initialize default settings if they don't exist
$default_settings = [
    'stops_per_hour_weight' => ['value' => '5', 'type' => 'number', 'description' => 'Weight factor for stops per hour in score calculation'],
    'weight_per_dump_weight' => ['value' => '3', 'type' => 'number', 'description' => 'Weight factor for weight per dump in score calculation'],
    'max_score' => ['value' => '100', 'type' => 'number', 'description' => 'Maximum possible score'],
    'early_start_time' => ['value' => '06:00:00', 'type' => 'time', 'description' => 'Time considered as early start for achievements'],
    'day_start_time' => ['value' => '04:00:00', 'type' => 'time', 'description' => 'Start time of the working day'],
    'day_end_time' => ['value' => '20:00:00', 'type' => 'time', 'description' => 'End time of the working day'],
    'min_route_duration' => ['value' => '2', 'type' => 'number', 'description' => 'Minimum route duration in hours'],
    'max_route_duration' => ['value' => '12', 'type' => 'number', 'description' => 'Maximum route duration in hours'],
    'enable_achievements' => ['value' => '1', 'type' => 'boolean', 'description' => 'Enable/disable achievement system'],
    'enable_public_leaderboard' => ['value' => '1', 'type' => 'boolean', 'description' => 'Enable/disable public leaderboard'],
];

foreach ($default_settings as $key => $setting) {
    $sql = "INSERT IGNORE INTO system_settings (setting_key, setting_value, type, description) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $key, $setting['value'], $setting['type'], $setting['description']);
    $stmt->execute();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_settings') {
        $success = true;
        
        foreach ($_POST['settings'] as $key => $value) {
            $sql = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $value, $key);
            if (!$stmt->execute()) {
                $success = false;
            }
        }
        
        if ($success) {
            $message = "Settings updated successfully";
        } else {
            $error = "Error updating some settings";
        }
    }
}

// Get current settings
$sql = "SELECT * FROM system_settings ORDER BY setting_key";
$settings = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Route Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Admin Header -->
        <header class="bg-gray-800 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">System Settings</h1>
                <nav class="space-x-4">
                    <a href="index.php" class="hover:text-gray-300">Dashboard</a>
                    <a href="manage_drivers.php" class="hover:text-gray-300">Manage Drivers</a>
                    <a href="logout.php" class="hover:text-gray-300">Logout</a>
                </nav>
            </div>
        </header>

        <main class="container mx-auto p-4">
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold">System Configuration</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_settings">

                        <!-- Scoring Settings -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-semibold mb-4">Score Calculation</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($settings as $setting): ?>
                                    <?php if (strpos($setting['setting_key'], 'weight') !== false): ?>
                                        <div>
                                            <label class="block text-gray-700 mb-2">
                                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                <span class="text-sm text-gray-500 block">
                                                    <?php echo $setting['description']; ?>
                                                </span>
                                            </label>
                                            <input type="number" 
                                                name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                value="<?php echo $setting['setting_value']; ?>"
                                                step="0.1"
                                                class="w-full p-2 border rounded">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Time Settings -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-semibold mb-4">Time Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($settings as $setting): ?>
                                    <?php if ($setting['type'] == 'time'): ?>
                                        <div>
                                            <label class="block text-gray-700 mb-2">
                                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                <span class="text-sm text-gray-500 block">
                                                    <?php echo $setting['description']; ?>
                                                </span>
                                            </label>
                                            <input type="time" 
                                                name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                value="<?php echo $setting['setting_value']; ?>"
                                                class="w-full p-2 border rounded">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Route Duration Settings -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-semibold mb-4">Route Duration Limits</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($settings as $setting): ?>
                                    <?php if (strpos($setting['setting_key'], 'route_duration') !== false): ?>
                                        <div>
                                            <label class="block text-gray-700 mb-2">
                                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                <span class="text-sm text-gray-500 block">
                                                    <?php echo $setting['description']; ?>
                                                </span>
                                            </label>
                                            <input type="number" 
                                                name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                value="<?php echo $setting['setting_value']; ?>"
                                                class="w-full p-2 border rounded">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Feature Toggles -->
                        <div class="border-b pb-6">
                            <h3 class="text-lg font-semibold mb-4">Feature Settings</h3>
                            <div class="space-y-4">
                                <?php foreach ($settings as $setting): ?>
                                    <?php if ($setting['type'] == 'boolean'): ?>
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                name="settings[<?php echo $setting['setting_key']; ?>]" 
                                                value="1"
                                                <?php echo $setting['setting_value'] == '1' ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-blue-600">
                                            <label class="ml-2 block text-gray-700">
                                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                                <span class="text-sm text-gray-500 block">
                                                    <?php echo $setting['description']; ?>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" 
                                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>