<?php
require_once 'check_auth.php';
require_once '../config.php';
require_once '../functions.php';

if (isset($_POST['export'])) {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="route_data_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Date', 'Driver', 'Start Time', 'End Time', 'Total Stops', 'Dumps', 'Weight', 'Score']);
    
    // Get data
    $sql = "SELECT r.*, d.name as driver_name 
            FROM routes r 
            JOIN drivers d ON r.driver_id = d.id 
            ORDER BY r.route_date DESC, r.start_time DESC";
            
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['route_date'],
            $row['driver_name'],
            $row['start_time'],
            $row['end_time'],
            $row['total_stops'],
            $row['dumps'],
            $row['total_weight'],
            $row['score']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - Route Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Admin Header -->
        <header class="bg-gray-800 text-white p-4">
            <div class="container mx-auto flex justify-between items-center">
                <h1 class="text-2xl font-bold">Export Data</h1>
                <nav class="space-x-4">
                    <a href="index.php" class="hover:text-gray-300">Dashboard</a>
                    <a href="manage_drivers.php" class="hover:text-gray-300">Manage Drivers</a>
                    <a href="logout.php" class="hover:text-gray-300">Logout</a>
                </nav>
            </div>
        </header>

        <main class="container mx-auto p-4">
            <div class="bg-white rounded-lg shadow">
                <div class="p-4 border-b">
                    <h2 class="text-lg font-bold">Export Options</h2>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <button type="submit" name="export" value="1"
                            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            Export All Route Data (CSV)
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>