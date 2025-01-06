<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RouteRival - The competitive platform for waste collection drivers to track routes, compete for top rankings, and earn achievements. Compare scores, monitor efficiency, and join the elite ranks of top performers in the waste industry.">
    <meta name="author" content="RouteRival">
    
    <title>RouteRival.com - <?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Track, Compete, Achieve'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/favicon.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Prevent FOUC (Flash of unstyled content) -->
    <style>
        .invisible-on-load {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .visible {
            opacity: 1;
        }
    </style>

    <!-- CSRF Token -->
    <?php
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <!-- Prevent XSS attacks -->
    <script nonce="<?php echo uniqid(); ?>">
        window.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.invisible-on-load').classList.add('visible');
        });

        // CSRF Token handling for AJAX requests
        document.addEventListener('DOMContentLoaded', function() {
            let token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            
            // Add CSRF token to all AJAX requests
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                let xhr = this;
                xhr.addEventListener('readystatechange', function() {
                    if (xhr.readyState === 1) {
                        if (method.toUpperCase() !== 'GET') {
                            xhr.setRequestHeader('X-CSRF-Token', token);
                        }
                    }
                });
                return oldXHROpen.apply(xhr, arguments);
            };
        });
    </script>
</head>
<body class="bg-gray-100 min-h-screen invisible-on-load">
    <header class="bg-blue-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-2xl font-bold hover:text-gray-200 transition-colors">RouteRival</a>
            <nav>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="flex gap-4 items-center">
                        <a href="dashboard.php" class="text-white hover:text-gray-200 transition-colors">Submit Route</a>
                        <a href="profile.php" class="text-white hover:text-gray-200 transition-colors">Profile</a>
                        <form action="logout.php" method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="text-white hover:text-gray-200 transition-colors">Logout</button>
                        </form>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-white hover:text-gray-200 transition-colors">Driver Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <main class="container mx-auto p-4">
    <?php
    // Display any flash messages
    if (isset($_SESSION['flash_message'])) {
        $message_type = $_SESSION['flash_type'] ?? 'info';
        $bg_color = $message_type === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700';
    ?>
        <div class="<?php echo $bg_color; ?> px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo h($_SESSION['flash_message']); ?></span>
            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                <svg class="fill-current h-6 w-6" role="button" onclick="this.parentElement.parentElement.remove()"
                     xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <title>Close</title>
                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
                </svg>
            </span>
        </div>
    <?php
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
    ?>