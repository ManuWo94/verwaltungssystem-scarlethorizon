<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

$user_id = $_SESSION['user_id'] ?? 'nicht angemeldet';

echo "<h1>Debug: Benachrichtigungen</h1>";
echo "<p><strong>Session User ID:</strong> " . htmlspecialchars($user_id) . "</p>";

// Test 1: Datei lesen
$notificationsFile = __DIR__ . '/data/notifications.json';
echo "<h2>Test 1: Datei-Pfad</h2>";
echo "<p>Pfad: " . $notificationsFile . "</p>";
echo "<p>Existiert: " . (file_exists($notificationsFile) ? 'JA' : 'NEIN') . "</p>";
echo "<p>Lesbar: " . (is_readable($notificationsFile) ? 'JA' : 'NEIN') . "</p>";

// Test 2: Datei-Inhalt
echo "<h2>Test 2: Datei-Inhalt (RAW)</h2>";
if (file_exists($notificationsFile)) {
    $content = file_get_contents($notificationsFile);
    echo "<pre>" . htmlspecialchars($content) . "</pre>";
    
    $decoded = json_decode($content, true);
    echo "<p>JSON valid: " . (json_last_error() === JSON_ERROR_NONE ? 'JA' : 'NEIN - ' . json_last_error_msg()) . "</p>";
    echo "<p>Anzahl Einträge: " . (is_array($decoded) ? count($decoded) : 'FEHLER') . "</p>";
}

// Test 3: getUserNotifications
echo "<h2>Test 3: getUserNotifications()</h2>";
try {
    $notifications = getUserNotifications($user_id, true, 10);
    echo "<p>Funktionsaufruf erfolgreich!</p>";
    echo "<p>Anzahl zurückgegeben: " . count($notifications) . "</p>";
    echo "<pre>" . htmlspecialchars(print_r($notifications, true)) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>FEHLER: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: countUnreadNotifications
echo "<h2>Test 4: countUnreadNotifications()</h2>";
try {
    $count = countUnreadNotifications($user_id);
    echo "<p>Funktionsaufruf erfolgreich!</p>";
    echo "<p>Anzahl ungelesen: " . $count . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>FEHLER: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: countUnreadNotifications mit type
echo "<h2>Test 5: countUnreadNotifications() mit type='test'</h2>";
try {
    $count = countUnreadNotifications($user_id, 'test');
    echo "<p>Funktionsaufruf erfolgreich!</p>";
    echo "<p>Anzahl test-Benachrichtigungen: " . $count . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>FEHLER: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 6: Alle Benachrichtigungen (auch gelesene)
echo "<h2>Test 6: getUserNotifications() - ALLE (auch gelesene)</h2>";
try {
    $allNotifications = getUserNotifications($user_id, false, 50);
    echo "<p>Funktionsaufruf erfolgreich!</p>";
    echo "<p>Anzahl ALLE: " . count($allNotifications) . "</p>";
    echo "<pre>" . htmlspecialchars(print_r($allNotifications, true)) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>FEHLER: " . htmlspecialchars($e->getMessage()) . "</p>";
}
