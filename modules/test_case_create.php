<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['user_id'] = '1';
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'Administrator';

require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

echo "<h1>Test Case Creation</h1>";

// Simuliere POST-Daten
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'defendant' => 'Test Angeklagter',
    'defendant_tg' => 'TG-1234',
    'charge' => 'Test Anklage',
    'incident_date' => '2026-01-01',
    'district' => 'Ost',
    'status' => 'Open'
];

echo "<h2>POST Daten:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>Nach sanitize:</h2>";
echo "defendant: '" . sanitize($_POST['defendant'] ?? '') . "'<br>";
echo "charge: '" . sanitize($_POST['charge'] ?? '') . "'<br>";
echo "incident_date: '" . sanitize($_POST['incident_date'] ?? '') . "'<br>";

$defendantName = sanitize($_POST['defendant'] ?? '');
$charge = sanitize($_POST['charge'] ?? '');
$incidentDate = sanitize($_POST['incident_date'] ?? '');

echo "<h2>Validierung:</h2>";
echo "empty(defendantName): " . (empty($defendantName) ? 'true' : 'false') . "<br>";
echo "empty(charge): " . (empty($charge) ? 'true' : 'false') . "<br>";
echo "empty(incidentDate): " . (empty($incidentDate) ? 'true' : 'false') . "<br>";

if (empty($defendantName) || empty($charge) || empty($incidentDate)) {
    echo "<p style='color:red'>FEHLER: Please fill in all required fields.</p>";
} else {
    echo "<p style='color:green'>SUCCESS: Alle Felder sind gefüllt!</p>";
}

echo "<h2>Test sanitize Funktion:</h2>";
echo "sanitize('Test'): '" . sanitize('Test') . "'<br>";
echo "sanitize(''): '" . sanitize('') . "'<br>";
echo "sanitize(null): '" . sanitize(null) . "'<br>";
