<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simuliere Login
$_SESSION['user_id'] = '1';
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'Administrator';

require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

echo "<!DOCTYPE html><html><head><title>Test</title></head><body>";
echo "<h1>Minimal Test</h1>";

// Minimal Page Content
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Load data
$cases = loadJsonData('civil_cases.json');
$plaintiffs = loadJsonData('parties.json');
$limitations = loadJsonData('limitations.json');
$users = getAllUsers();

echo "<p>Data loaded successfully!</p>";
echo "<p>Cases: " . count($cases) . "</p>";
echo "<p>Users: " . count($users) . "</p>";

// Include header
include '../includes/header.php';

echo "<div class='container'>";
echo "<h2>Test passed</h2>";
echo "</div>";

include '../includes/footer.php';

echo "</body></html>";
