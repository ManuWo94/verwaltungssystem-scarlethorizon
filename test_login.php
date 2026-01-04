<?php
/**
 * Test login script to test warrant module functionality
 */

// Start session
session_start();

// Include functions
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Get username from query parameter or use default
$username = isset($_GET['username']) ? $_GET['username'] : 'admin';

// Log in as specified user or default to admin
$users = loadJsonData('users.json');
$user = null;

foreach ($users as $u) {
    if ($u['username'] === $username) {
        $user = $u;
        break;
    }
}

if ($user) {
    // Set up session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['authenticated'] = true;
    
    echo "Logged in as " . $user['username'] . " (" . implode(', ', $user['roles']) . ")";
    echo "<p><a href='modules/warrants.php'>Go to Warrants Module</a></p>";
} else {
    echo "Admin user not found. Please create an admin user first.";
}
?>