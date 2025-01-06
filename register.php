<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Set page title
$page_title = "Register";

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid request');
        }

        // Validate and sanitize input
        $username = trim(filter_var($_POST['username'], FILTER_SANITIZE_STRING));
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $name = trim(filter_var($_POST['name'], FILTER_SANITIZE_STRING));
        $company = trim(filter_var($_POST['company'], FILTER_SANITIZE_STRING));
        $line_of_business = trim(filter_var($_POST['line_of_business'], FILTER_SANITIZE_STRING));
        $years_experience = filter_var($_POST['years_experience'], FILTER_VALIDATE_INT);
        $public_profile = isset($_POST['public_profile']) ? 1 : 0;

        // Validate username
        $stmt = $conn->prepare("SELECT id FROM drivers WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Username already exists");
        }

        // Password validation
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }
        if ($password !== $confirm_password) {
            throw new Exception("Passwords do not match");
        }
        
        // Other validations
        if (empty($name)) {
            throw new Exception("Name is required");
        }
        if ($years_experience === false || $years_experience < 0) {
            throw new Exception("Invalid years of experience");
        }

        // Create new driver
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO drivers (
                    username, 
                    password_hash, 
                    name, 
                    company, 
                    line_of_business, 
                    years_experience, 
                    public_profile,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Registration query failed: " . $conn->error);
        }

        $stmt->bind_param("sssssii", 
            $username, 
            $password_hash, 
            $name, 
            $company, 
            $line_of_business, 
            $years_experience, 
            $public_profile
        );

        if (!$stmt->execute()) {
            throw new Exception("Registration failed: " . $stmt->error);
        }

        $_SESSION['success'] = "Registration successful! Please login.";
        header("Location: login.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    $error = $e->getMessage();
}
?>

<?php include 'header.php'; ?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-md p-6 mt-8">
        <h2 class="text-2xl font-bold mb-6 text-center">Driver Registration</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Basic Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Basic Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2" for="username">Username*</label>
                        <input type="text" 
                               id="username"
                               name="username" 
                               required 
                               value="<?php echo isset($_POST['username']) ? h($_POST['username']) : ''; ?>"
                               class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="name">Full Name*</label>
                        <input type="text" 
                               id="name"
                               name="name" 
                               required 
                               value="<?php echo isset($_POST['name']) ? h($_POST['name']) : ''; ?>"
                               class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- Company Information -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Company Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2" for="company">Company Name</label>
                        <input type="text" 
                               id="company"
                               name="company" 
                               value="<?php echo isset($_POST['company']) ? h($_POST['company']) : ''; ?>"
                               class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="line_of_business">Line of Business</label>
                        <select name="line_of_business" 
                                id="line_of_business"
                                class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                            <option value="">Select Line of Business</option>
                            <?php
                            $businesses = ['Commercial', 'Residential', 'Industrial', 'Mixed'];
                            foreach ($businesses as $business): ?>
                                <option value="<?php echo h($business); ?>" 
                                    <?php echo isset($_POST['line_of_business']) && $_POST['line_of_business'] == $business ? 'selected' : ''; ?>>
                                    <?php echo h($business); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2" for="years_experience">Years of Experience</label>
                    <input type="number" 
                           id="years_experience"
                           name="years_experience" 
                           min="0" 
                           max="50"
                           value="<?php echo isset($_POST['years_experience']) ? h($_POST['years_experience']) : '0'; ?>"
                           class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Security</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2" for="password">Password*</label>
                        <div class="relative">
                            <input type="password" 
                                   id="password"
                                   name="password" 
                                   required 
                                   class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                            <button type="button" 
                                    onclick="togglePassword('password')"
                                    class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Must be at least 6 characters</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2" for="confirm_password">Confirm Password*</label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirm_password"
                                   name="confirm_password" 
                                   required 
                                   class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                            <button type="button" 
                                    onclick="togglePassword('confirm_password')"
                                    class="absolute right-2 top-2.5 text-gray-500 hover:text-gray-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Privacy Settings -->
            <div class="space-y-4">
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="public_profile"
                           name="public_profile" 
                           value="1" 
                           <?php echo !isset($_POST['public_profile']) || $_POST['public_profile'] ? 'checked' : ''; ?>
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label class="ml-2 block text-sm text-gray-700" for="public_profile">
                        Make my profile public
                        <span class="block text-xs text-gray-500">
                            Your name and achievements will be visible on the leaderboard
                        </span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col items-center space-y-4">
                <button type="submit" 
                    class="w-full md:w-auto px-8 py-3 bg-blue-600 text-white rounded hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                    Register
                </button>
                
                <p class="text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="text-blue-600 hover:underline">Login here</a>
                </p>
            </div>
        </form>
    </div>
</div>

<script>
function validateForm() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const confirm_password = document.getElementById('confirm_password').value;
    const name = document.getElementById('name').value.trim();
    const years_experience = document.getElementById('years_experience').value;

    if (!username || !password || !confirm_password || !name) {
        showError('Please fill in all required fields');
        return false;
    }

    if (password.length < 6) {
        showError('Password must be at least 6 characters long');
        return false;
    }

    if (password !== confirm_password) {
        showError('Passwords do not match');
        return false;
    }

    if (years_experience < 0) {
        showError('Years of experience cannot be negative');
        return false;
    }

    return true;
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
}
</script>

<?php include 'footer.php'; ?>