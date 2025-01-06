<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';

// Set page title
$page_title = "Home";

try {
    // Get system stats for display
    $stats = getSystemStats($conn);
    
} catch (Exception $e) {
    error_log("Index page error: " . $e->getMessage());
    $error = "An error occurred loading the page. Please try again later.";
}

include 'header.php';
?>

<div class="min-h-screen flex flex-col">
    <main class="flex-grow">
        <div class="max-w-2xl mx-auto">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo h($error); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-md">
                <div class="p-4 border-b flex justify-between items-center">
                    <h2 class="text-lg font-bold">Top Drivers</h2>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                            Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                            Driver Login
                        </a>
                    <?php endif; ?>
                </div>

                <div class="p-4 border-b">
                    <div class="flex justify-center space-x-4">
                        <button onclick="changePeriod('today')" 
                                class="period-btn active px-4 py-2 rounded transition-colors" 
                                data-period="today">Today</button>
                        <button onclick="changePeriod('week')" 
                                class="period-btn px-4 py-2 rounded transition-colors" 
                                data-period="week">This Week</button>
                        <button onclick="changePeriod('month')" 
                                class="period-btn px-4 py-2 rounded transition-colors" 
                                data-period="month">This Month</button>
                    </div>
                </div>

                <div id="leaderboardContent" class="p-4">
                    <div class="flex justify-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                    </div>
                </div>

                <div class="p-4 border-t text-center text-sm text-gray-500">
                    Last updated: <span id="lastUpdated">Just now</span>
                    <button onclick="refreshLeaderboard()" 
                            class="ml-2 text-blue-600 hover:text-blue-800 transition-colors">
                        Refresh
                    </button>
                </div>
            </div>

            <!-- System Stats -->
            <?php if (!empty($stats)): ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8 mb-8">
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-gray-500 text-sm">Total Drivers</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($stats['total_drivers']); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-gray-500 text-sm">Total Routes</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($stats['total_routes']); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-gray-500 text-sm">Total Stops</h3>
                    <p class="text-2xl font-bold"><?php echo formatNumber($stats['total_stops']); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-md p-4">
                    <h3 class="text-gray-500 text-sm">Waste Collected</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($stats['total_weight'], 1); ?> tons</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</div>

<script>
let currentPeriod = 'today';
let isLoading = false;

// Update button styles
function updateButtonStyles() {
    document.querySelectorAll('.period-btn').forEach(btn => {
        const isPeriodActive = btn.dataset.period === currentPeriod;
        btn.classList.toggle('bg-blue-600', isPeriodActive);
        btn.classList.toggle('text-white', isPeriodActive);
        btn.classList.toggle('text-gray-600', !isPeriodActive);
        btn.classList.toggle('hover:bg-gray-100', !isPeriodActive);
    });
}

// Initialize the UI
updateButtonStyles();

async function fetchLeaderboard() {
    if (isLoading) return;
    isLoading = true;
    
    const content = document.getElementById('leaderboardContent');
    content.innerHTML = `
        <div class="flex justify-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
    `;

    try {
        const response = await fetch(`get_leaderboard.php?period=${currentPeriod}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        
        if (result.success) {
            renderLeaderboard(result.data);
            document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
        } else {
            throw new Error(result.error || 'Failed to load leaderboard');
        }
    } catch (error) {
        console.error("Leaderboard error:", error);
        content.innerHTML = `
            <div class="text-center text-red-600 py-4">
                Failed to load leaderboard. 
                <button onclick="fetchLeaderboard()" class="text-blue-600 hover:text-blue-800 ml-2">
                    Try Again
                </button>
            </div>
        `;
    } finally {
        isLoading = false;
    }
}

function renderLeaderboard(data) {
    const content = document.getElementById('leaderboardContent');
    
    if (!data || data.length === 0) {
        content.innerHTML = '<p class="text-gray-500 text-center py-4">No data available for this period.</p>';
        return;
    }

    let html = '<div class="space-y-4">';
    data.forEach((entry, index) => {
        html += `
            <div class="border rounded p-4 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="font-bold">
                            #${(index + 1)} - 
                            <a href="driver_profile.php?id=${entry.driver_id}" 
                               class="text-blue-600 hover:text-blue-800 hover:underline">
                                ${escapeHtml(entry.name)}
                            </a>
                        </h4>
                        <p class="text-sm text-gray-600">
                            ${formatNumber(entry.total_stops)} stops · 
                            ${formatNumber(entry.dumps)} dumps · 
                            ${entry.total_weight} tons
                        </p>
                        <p class="text-xs text-gray-500">
                            ${entry.start_time || ''} ${entry.end_time ? '- ' + entry.end_time : ''}
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-600">
                            ${entry.score}
                        </div>
                        <div class="text-sm text-gray-500">Score</div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    content.innerHTML = html;
}

function changePeriod(period) {
    if (currentPeriod === period) return;
    currentPeriod = period;
    updateButtonStyles();
    fetchLeaderboard();
}

function refreshLeaderboard() {
    fetchLeaderboard();
}

// Helper functions
function formatNumber(num) {
    return Number(num).toLocaleString();
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Initial load
document.addEventListener('DOMContentLoaded', fetchLeaderboard);
</script>
<?php
// Don't include footer.php here as it's already included in the flex container above
?>