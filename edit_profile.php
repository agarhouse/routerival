<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$error = '';

try {
    // Get current user info
    $sql = "SELECT * FROM drivers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare user query: " . $conn->error);
    }

    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $driver = $stmt->get_result()->fetch_assoc();

    if (!$driver) {
        throw new Exception("Driver not found");
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_profile':
                    // Validate and sanitize input
                    $name = trim(filter_var($_POST['name'], FILTER_SANITIZE_STRING));
                    $username = trim(filter_var($_POST['username'], FILTER_SANITIZE_STRING));
                    $company = trim(filter_var($_POST['company'], FILTER_SANITIZE_STRING));
                    $line_of_business = trim(filter_var($_POST['line_of_business'], FILTER_SANITIZE_STRING));
                    $years_experience = filter_var($_POST['years_experience'], FILTER_VALIDATE_INT);
                    $public_profile = isset($_POST['public_profile']) ? 1 : 0;
                    
                    // Basic validation
                    if (empty($name) || empty($username)) {
                        throw new Exception("Name and username are required");
                    }
                    
                    if ($years_experience === false || $years_experience < 0) {
                        throw new Exception("Invalid years of experience");
                    }
                    
                    // Check if username is already taken by another user
                    $sql = "SELECT id FROM drivers WHERE username = ? AND id != ?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare username check: " . $conn->error);
                    }
                    
                    $stmt->bind_param("si", $username, $_SESSION['user_id']);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        throw new Exception("Username is already taken");
                    }

                    // Update profile
                    $sql = "UPDATE drivers SET 
                            name = ?, 
                            username = ?, 
                            company = ?, 
                            line_of_business = ?, 
                            years_experience = ?,
                            public_profile = ?
                            WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare update query: " . $conn->error);
                    }
                    
                    $stmt->bind_param("ssssiii", 
                        $name, 
                        $username, 
                        $company, 
                        $line_of_business, 
                        $years_experience,
                        $public_profile,
                        $_SESSION['user_id']
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update profile: " . $stmt->error);
                    }
                    
                    $message = "Profile updated successfully";
                    // Update local driver variable
                    $driver['name'] = $name;
                    $driver['username'] = $username;
                    $driver['company'] = $company;
                    $driver['line_of_business'] = $line_of_business;
                    $driver['years_experience'] = $years_experience;
                    $driver['public_profile'] = $public_profile;
                    break;

                case 'change_password':
                    $current_password = $_POST['current_password'];
                    $new_password = $_POST['new_password'];
                    $confirm_password = $_POST['confirm_password'];

                    // Validate password change
                    if (!password_verify($current_password, $driver['password_hash'])) {
                        throw new Exception("Current password is incorrect");
                    }
                    
                    if ($new_password !== $confirm_password) {
                        throw new Exception("New passwords do not match");
                    }
                    
                    if (strlen($new_password) < 6) {
                        throw new Exception("New password must be at least 6 characters");
                    }

                    // Update password
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql = "UPDATE drivers SET password_hash = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Failed to prepare password update: " . $conn->error);
                    }
                    
                    $stmt->bind_param("si", $password_hash, $_SESSION['user_id']);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update password: " . $stmt->error);
                    }
                    
                    $message = "Password changed successfully";
                    break;
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Edit profile error: " . $e->getMessage());
}
?>

<?php include 'header.php'; ?>

<div class="max-w-2xl mx-auto">
    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo h($error); ?>
        </div>
    <?php endif; ?>

    <!-- Profile Information -->
    <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="p-4 border-b">
            <h2 class="text-lg font-bold">Edit Profile</h2>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4" onsubmit="return validateProfileForm()">
                <input type="hidden" name="action" value="update_profile">
                
                <!-- Basic Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Full Name</label>
                        <input type="text" name="name" value="<?php echo h($driver['name']); ?>" required
                            class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Username</label>
                        <input type="text" name="username" value="<?php echo h($driver['username']); ?>" required
                            class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                    </div>
                </div>

                <!-- Company Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Company</label>
                        <input type="text" name="company" value="<?php echo h($driver['company']); ?>"
                            class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Line of Business</label>
                        <select name="line_of_business" 
                            class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                            <option value="">Select Line of Business</option>
                            <?php
                            $businesses = ['Commercial', 'Residential', 'Industrial', 'Mixed'];
                            foreach ($businesses as $business): ?>
                                <option value="<?php echo h($business); ?>" 
                                    <?php echo $driver['line_of_business'] == $business ? 'selected' : ''; ?>>
                                    <?php echo h($business); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">Years of Experience</label>
                    <input type="number" name="years_experience" value="<?php echo (int)$driver['years_experience']; ?>" min="0"
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>

                <!-- Privacy Settings -->
                <div class="mt-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="public_profile" value="1" 
                            <?php echo $driver['public_profile'] ? 'checked' : ''; ?>
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <span class="ml-2 text-gray-700">Make my profile public</span>
                    </label>
                    <p class="text-sm text-gray-500 mt-1">
                        When enabled, other users can view your profile, achievements, and statistics.
                    </p>
                </div>

                <div class="pt-4">
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="p-4 border-b">
            <h2 class="text-lg font-bold">Change Password</h2>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4" onsubmit="return validatePasswordForm()">
                <input type="hidden" name="action" value="change_password">
                
                <div>
                    <label class="block text-gray-700 mb-2">Current Password</label>
                    <input type="password" name="current_password" id="current_password" required
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2">New Password</label>
                    <input type="password" name="new_password" id="new_password" required
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                    <p class="text-sm text-gray-500 mt-1">Must be at least 6 characters</p>
                </div>
                
                <div>
                    <label class="block text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required
                        class="w-full p-2 border rounded focus:border-blue-500 focus:outline-none">
                </div>

                <div class="pt-4">
                    <button type="submit"
                        class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-6 text-center">
        <a href="profile.php" class="text-blue-600 hover:underline">
            Back to Profile
        </a>
    </div>
</div>

<script>
function validateProfileForm() {
    const name = document.querySelector('input[name="name"]').value.trim();
    const username = document.querySelector('input[name="username"]').value.trim();
    const yearsExperience = document.querySelector('input[name="years_experience"]').value;

    if (!name || !username) {
        alert('Name and username are required');
        return false;
    }

    if (yearsExperience < 0) {
        alert('Years of experience cannot be negative');
        return false;
    }

    return true;
}

function validatePasswordForm() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (!currentPassword || !newPassword || !confirmPassword) {
        alert('All password fields are required');
        return false;
    }

    if (newPassword.length < 6) {
        alert('New password must be at least 6 characters');
        return false;
    }

    if (newPassword !== confirmPassword) {
        alert('New passwords do not match');
        return false;
    }

    return true;
}
</script>

<?php include 'footer.php'; ?>