<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
session_start();
require_once 'config.php';

// Define the h() function for escaping output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Set page title for header
$page_title = "Login";

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
   header("Location: dashboard.php");
   exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid request');
        }

        // Validate and sanitize input
        $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($username)) {
            throw new Exception('Username is required');
        }

        $password = $_POST['password'];
        if (empty($password)) {
            throw new Exception('Password is required');
        }

        // Prepare and execute query
        $sql = "SELECT id, password_hash FROM drivers WHERE username = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                // Set session
                $_SESSION['user_id'] = $row['id'];
                
                // Update last login timestamp
                $update_sql = "UPDATE drivers SET last_login = NOW() WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if ($update_stmt) {
                    $update_stmt->bind_param("i", $row['id']);
                    $update_stmt->execute();
                }

                // Regenerate session ID for security
                session_regenerate_id(true);
                
                header("Location: dashboard.php");
                exit();
            }
        }
        
        // If we get here, login failed
        $error = "Invalid username or password";
        
        // Log failed attempt
        error_log("Failed login attempt for username: " . $username);
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    $error = "An error occurred. Please try again.";
}
?>

<?php include 'header.php'; ?>

<div class="max-w-md mx-auto mt-10">
   <div class="bg-white p-6 rounded-lg shadow-md">
       <h2 class="text-2xl font-bold mb-6 text-center">Welcome to RouteRival</h2>
       
       <?php if (isset($error)): ?>
           <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
               <?php echo h($error); ?>
           </div>
       <?php endif; ?>

       <?php if (isset($_SESSION['success'])): ?>
           <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
               <?php 
               echo h($_SESSION['success']);
               unset($_SESSION['success']);
               ?>
           </div>
       <?php endif; ?>

       <form method="POST" class="space-y-4" onsubmit="return validateForm()">
           <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
           
           <div>
               <label class="block text-gray-700 mb-2" for="username">Username</label>
               <input type="text" 
                      id="username"
                      name="username" 
                      required 
                      autocomplete="username"
                      class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none"
                      placeholder="Enter your username"
                      value="<?php echo isset($_POST['username']) ? h($_POST['username']) : ''; ?>">
           </div>
           
           <div>
               <label class="block text-gray-700 mb-2" for="password">Password</label>
               <div class="relative">
                   <input type="password" 
                          id="password"
                          name="password" 
                          required
                          autocomplete="current-password"
                          class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none"
                          placeholder="Enter your password">
                   <button type="button" 
                           onclick="togglePassword()"
                           class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
                       <svg class="h-5 w-5" id="passwordToggleIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                       </svg>
                   </button>
               </div>
           </div>

           <div class="pt-2">
               <button type="submit" 
                       class="w-full bg-blue-600 text-white p-3 rounded hover:bg-blue-700 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                   Login
               </button>
           </div>

           <div class="text-center pt-4">
               <p class="text-gray-600">
                   Don't have an account? 
                   <a href="register.php" class="text-blue-600 hover:underline font-medium">
                       Register here
                   </a>
               </p>
           </div>
       </form>
       
       <div class="mt-6 pt-6 border-t border-gray-200">
           <p class="text-sm text-gray-500 text-center">
               Track your routes, compete with fellow drivers, and earn achievements.
           </p>
       </div>
   </div>
</div>

<script>
function validateForm() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    if (!username || !password) {
        showError('Please fill in all fields');
        return false;
    }
    
    return true;
}

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const icon = document.getElementById('passwordToggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        `;
    } else {
        passwordInput.type = 'password';
        icon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        `;
    }
}
</script>

<?php include 'footer.php'; ?>