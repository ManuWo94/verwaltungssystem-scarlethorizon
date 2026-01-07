<?php
// Aktiviere Error Reporting für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><body>";
echo "<h1>Civil Cases Debug</h1>";

session_start();

// Simuliere Admin wenn nicht eingeloggt
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = '1';
    $_SESSION['username'] = 'admin';
    $_SESSION['role'] = 'Administrator';
    echo "<p>Session simuliert als Admin</p>";
}

require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

echo "<p>Includes geladen</p>";

// Test Permission Check
try {
    checkPermissionOrDie('civil_cases', 'view');
    echo "<p>Permission Check OK</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>Permission Error: " . $e->getMessage() . "</p>";
    die();
}

// Test Data Loading
echo "<h2>Daten laden...</h2>";

try {
    $cases = loadJsonData('civil_cases.json');
    echo "<p>Cases geladen: " . count($cases) . "</p>";
    
    $plaintiffs = loadJsonData('parties.json');
    echo "<p>Plaintiffs geladen: " . count($plaintiffs) . "</p>";
    
    $limitations = loadJsonData('limitations.json');
    echo "<p>Limitations geladen: " . count($limitations) . "</p>";
    
    $users = getAllUsers();
    echo "<p>Users geladen: " . count($users) . "</p>";
    
    echo "<h2>Alle Tests erfolgreich!</h2>";
    echo "<p><a href='civil_cases.php'>Zur echten civil_cases.php</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
