<?php
// Enable error reporting for debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Initialize session and include required files
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

try {
    // Get current user's name
    $stmt = $conn->prepare("SELECT name FROM drivers WHERE id = ?");
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

    // Get today's leaderboard
    $today = date('Y-m-d');
    $leaderboard = getLeaderboard($conn, $today);

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "An error occurred loading the dashboard. Please try again later.";
}

include 'header.php';
?>

<div class="max-w-2xl mx-auto">
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo h($error); ?>
        </div>
    <?php endif; ?>

    <!-- Welcome Message -->
    <h2 class="text-xl font-bold mb-6">Welcome, <?php echo h($driver['name'] ?? 'Driver'); ?></h2>

    <!-- Route Entry Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h3 class="text-lg font-bold mb-4">Log Today's Route</h3>
        
        <form action="submit_route.php" method="POST" class="space-y-4" onsubmit="return validateForm()">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">Start Time</label>
                    <input type="time" name="start_time" id="start_time" required 
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2">End Time</label>
                    <input type="time" name="end_time" id="end_time" required 
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-2">Total Stops</label>
                    <input type="number" name="total_stops" id="total_stops" required min="1"
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2">Number of Dumps</label>
                    <input type="number" name="dumps" id="dumps" required min="1"
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-gray-700 mb-2">Total Weight (tons)</label>
                <input type="number" name="total_weight" id="total_weight" required step="0.1" min="0.1"
                    class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
            </div>

            <button type="submit" 
                class="w-full bg-blue-600 text-white p-3 rounded font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Submit Route
            </button>
        </form>
    </div>

    <!-- Leaderboard -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-lg font-bold mb-4">Today's Leaderboard</h3>
        
        <?php if (empty($leaderboard)): ?>
            <p class="text-gray-500 text-center py-4">No routes logged today yet.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($leaderboard as $rank => $entry): ?>
                    <div class="border rounded p-4 <?php echo $entry['driver_id'] == $_SESSION['user_id'] ? 'bg-blue-50' : ''; ?>">
                        <div class="flex justify-between items-center">
                            <div>
                                <h4 class="font-bold">
                                    #<?php echo $rank + 1; ?> - <?php echo h($entry['name']); ?>
                                </h4>
                                <p class="text-sm text-gray-600">
                                    <?php echo (int)$entry['total_stops']; ?> stops · 
                                    <?php echo (int)$entry['dumps']; ?> dumps · 
                                    <?php echo number_format($entry['total_weight'], 1); ?> tons
                                </p>
                                <p class="text-xs text-gray-500">
                                    <?php echo formatTime($entry['start_time']); ?> - 
                                    <?php echo formatTime($entry['end_time']); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-blue-600">
                                    <?php echo number_format($entry['score'], 1); ?>
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

<script>
function validateForm() {
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const totalStops = parseInt(document.getElementById('total_stops').value);
    const dumps = parseInt(document.getElementById('dumps').value);
    const totalWeight = parseFloat(document.getElementById('total_weight').value);

    if (startTime >= endTime) {
        alert('End time must be after start time');
        return false;
    }

    if (totalStops < dumps) {
        alert('Total stops cannot be less than number of dumps');
        return false;
    }

    if (totalWeight <= 0) {
        alert('Total weight must be greater than 0');
        return false;
    }

    return true;
}
</script>

<?php include 'footer.php'; ?>