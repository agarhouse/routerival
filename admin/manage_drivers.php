<?php
require_once 'check_auth.php';
require_once '../config.php';
require_once '../functions.php';

$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $username = $conn->real_escape_string($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $name = $conn->real_escape_string($_POST['name']);
                
                $sql = "INSERT INTO drivers (username, password_hash, name) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $username, $password, $name);
                
                if ($stmt->execute()) {
                    $message = "Driver added successfully";
                } else {
                    $message = "Error adding driver";
                }
                break;
                
            case 'reset_password':
                $driver_id = (int)$_POST['driver_id'];
                $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                
                $sql = "UPDATE drivers SET password_hash = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $password, $driver_id);
                
                if ($stmt->execute()) {
                    $message = "Password reset successfully";
                } else {
                    $message = "Error resetting password";
                }
                break;
        }
    }
}

// Get all drivers
$sql = "SELECT d.*, 
        COUNT(r.id) as total_routes,
        AVG(r.score) as avg_score
        FROM drivers d
        LEFT JOIN routes r ON d.id = r.driver_id
        GROUP BY d.id
        ORDER BY d.name";
$drivers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - Route Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Admin Header -->
        <header class="bg-gray-800 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">Manage Drivers</h1>
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

            <!-- Add New Driver -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold">Add New Driver</h2>
                </div>
                <div class="p-4">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="hidden" name="action" value="add">
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Username</label>
                            <input type="text" name="username" required 
                                class="w-full p-2 border rounded">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Password</label>
                            <input type="password" name="password" required 
                                class="w-full p-2 border rounded">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="name" required 
                                class="w-full p-2 border rounded">
                        </div>
                        
                        <div class="md:col-span-3">
                            <button type="submit" 
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                Add Driver
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Drivers List -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold">Current Drivers</h2>
                </div>
                <div class="p-4">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left">Name</th>
                                    <th class="px-4 py-2 text-left">Username</th>
                                    <th class="px-4 py-2 text-center">Total                             Routes</th>
                                    <th class="px-4 py-2 text-center">Avg Score</th>
                                    <th class="px-4 py-2 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($drivers as $driver): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3"><?php echo h($driver['name']); ?></td>
                                        <td class="px-4 py-3"><?php echo h($driver['username']); ?></td>
                                        <td class="px-4 py-3 text-center"><?php echo $driver['total_routes']; ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <?php echo $driver['avg_score'] ? number_format($driver['avg_score'], 1) : '-'; ?>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button onclick="showResetPassword(<?php echo $driver['id']; ?>)"
                                                class="bg-yellow-500 text-white px-3 py-1 rounded text-sm hover:bg-yellow-600">
                                                Reset Password
                                            </button>
                                            <a href="view_driver.php?id=<?php echo $driver['id']; ?>" 
                                                class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 ml-2">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetPasswordModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <h3 class="text-lg font-bold mb-4">Reset Password</h3>
            <form method="POST" class="space-y-4">
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