<?php
require_once 'check_auth.php';
require_once '../config.php';
require_once '../functions.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $name = $conn->real_escape_string($_POST['name']);
                $description = $conn->real_escape_string($_POST['description']);
                $icon = $conn->real_escape_string($_POST['icon']);
                $requirement_type = $conn->real_escape_string($_POST['requirement_type']);
                $requirement_value = (int)$_POST['requirement_value'];

                $sql = "INSERT INTO achievements (name, description, icon, requirement_type, requirement_value) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $name, $description, $icon, $requirement_type, $requirement_value);
                
                if ($stmt->execute()) {
                    $message = "Achievement added successfully";
                } else {
                    $error = "Error adding achievement";
                }
                break;

            case 'edit':
                $id = (int)$_POST['achievement_id'];
                $name = $conn->real_escape_string($_POST['name']);
                $description = $conn->real_escape_string($_POST['description']);
                $icon = $conn->real_escape_string($_POST['icon']);
                $requirement_type = $conn->real_escape_string($_POST['requirement_type']);
                $requirement_value = (int)$_POST['requirement_value'];

                $sql = "UPDATE achievements 
                        SET name = ?, description = ?, icon = ?, requirement_type = ?, requirement_value = ? 
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssii", $name, $description, $icon, $requirement_type, $requirement_value, $id);
                
                if ($stmt->execute()) {
                    $message = "Achievement updated successfully";
                } else {
                    $error = "Error updating achievement";
                }
                break;
        }
    }
}

// Get all achievements with earned count
$sql = "SELECT a.*, 
        COUNT(DISTINCT da.driver_id) as times_earned
        FROM achievements a
        LEFT JOIN driver_achievements da ON a.id = da.achievement_id
        GROUP BY a.id
        ORDER BY a.requirement_type, a.requirement_value";
$achievements = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Achievements - Route Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Admin Header -->
        <header class="bg-gray-800 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">Manage Achievements</h1>
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

            <!-- Add New Achievement -->
            <div class="bg-white rounded-lg shadow-md mb-8">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold">Add New Achievement</h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Name</label>
                                <input type="text" name="name" required 
                                    class="w-full p-2 border rounded">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Icon (Emoji)</label>
                                <input type="text" name="icon" required 
                                    class="w-full p-2 border rounded">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2">Description</label>
                            <textarea name="description" required 
                                class="w-full p-2 border rounded"></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Requirement Type</label>
                                <select name="requirement_type" required 
                                    class="w-full p-2 border rounded">
                                    <option value="total_routes">Total Routes</option>
                                    <option value="total_stops">Total Stops</option>
                                    <option value="total_weight">Total Weight</option>
                                    <option value="high_score">High Score</option>
                                    <option value="early_starts">Early Starts</option>
                                    <option value="streak_score">Score Streak</option>
                                    <option value="daily_streak">Daily Streak</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Requirement Value</label>
                                <input type="number" name="requirement_value" required 
                                    class="w-full p-2 border rounded">
                            </div>
                        </div>

                        <button type="submit" 
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Add Achievement
                        </button>
                    </form>
                </div>
            </div>

            <!-- Achievements List -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold">Current Achievements</h2>
                </div>
                <div class="p-4">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-4 py-2 text-left">Icon</th>
                                    <th class="px-4 py-2 text-left">Name</th>
                                    <th class="px-4 py-2 text-left">Description</th>
                                    <th class="px-4 py-2 text-left">Type</th>
                                    <th class="px-4 py-2 text-center">Value</th>
                                    <th class="px-4 py-2 text-center">Times Earned</th>
                                    <th class="px-4 py-2 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($achievements as $achievement): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-2xl"><?php echo $achievement['icon']; ?></td>
                                        <td class="px-4 py-3"><?php echo h($achievement['name']); ?></td>
                                        <td class="px-4 py-3"><?php echo h($achievement['description']); ?></td>
                                        <td class="px-4 py-3"><?php echo h($achievement['requirement_type']); ?></td>
                                        <td class="px-4 py-3 text-center"><?php echo $achievement['requirement_value']; ?></td>
                                        <td class="px-4 py-3 text-center"><?php echo $achievement['times_earned']; ?></td>
                                        <td class="px-4 py-3 text-center">
                                            <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($achievement)); ?>)"
                                                class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                                                Edit
                                            </button>
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

    <!-- Edit Achievement Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-2xl w-full">
            <h3 class="text-lg font-bold mb-4">Edit Achievement</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="achievement_id" id="editAchievementId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Name</label>
                        <input type="text" name="name" id="editName" required 
                            class="w-full p-2 border rounded">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Icon</label>
                        <input type="text" name="icon" id="editIcon" required 
                            class="w-full p-2 border rounded">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="editDescription" required 
                        class="w-full p-2 border rounded"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Requirement Type</label>
                        <select name="requirement_type" id="editRequirementType" required 
                            class="w-full p-2 border rounded">
                            <option value="total_routes">Total Routes</option>
                            <option value="total_stops">Total Stops</option>
                            <option value="total_weight">Total Weight</option>
                            <option value="high_score">High Score</option>
                            <option value="early_starts">Early Starts</option>
                            <option value="streak_score">Score Streak</option>
                            <option value="daily_streak">Daily Streak</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Requirement Value</label>
                        <input type="number" name="requirement_value" id="editRequirementValue" required 
                            class="w-full p-2 border rounded">
                    </div>
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="hideEditModal()"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" 
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showEditModal(achievement) {
            document.getElementById('editAchievementId').value = achievement.id;
            document.getElementById('editName').value = achievement.name;
            document.getElementById('editIcon').value = achievement.icon;
            document.getElementById('editDescription').value = achievement.description;
            document.getElementById('editRequirementType').value = achievement.requirement_type;
            document.getElementById('editRequirementValue').value = achievement.requirement_value;
            document.getElementById('editModal').classList.remove('hidden');
        }

        function hideEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>