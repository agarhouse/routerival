<?php
session_start();
require_once 'config.php';

try {
    // Clear all session data
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();

    // Set success message in a temporary cookie
    setcookie('logout_success', '1', time() + 5, '/');

    // Redirect to login page
    header("Location: login.php");
    exit();

} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());

    // Redirect to login page even if there was an error
    header("Location: login.php");
    exit();
}
?>