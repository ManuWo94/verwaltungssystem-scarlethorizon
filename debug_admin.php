<?php
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

// Simuliere eingeloggten Admin
$user_id = 'admin';

echo "<h1>Debug: Benachrichtigungen für user_id = 'admin'</h1>";

// Test: getUserNotifications
echo "<h2>getUserNotifications('admin', true, 10)</h2>";
$notifications = getUserNotifications($user_id, true, 10);
echo "<p>Anzahl zurückgegeben: " . count($notifications) . "</p>";
echo "<pre>" . print_r($notifications, true) . "</pre>";

// Test: countUnreadNotifications
echo "<h2>countUnreadNotifications('admin')</h2>";
$count = countUnreadNotifications($user_id);
echo "<p>Anzahl: " . $count . "</p>";

// Test: mit type
echo "<h2>countUnreadNotifications('admin', 'test')</h2>";
$count = countUnreadNotifications($user_id, 'test');
echo "<p>Anzahl: " . $count . "</p>";

// Debug: Zeige RAW-Daten
echo "<h2>RAW Datei-Inhalt</h2>";
$content = file_get_contents('data/notifications.json');
echo "<pre>" . htmlspecialchars($content) . "</pre>";
