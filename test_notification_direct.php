<?php
/**
 * Direkter Benachrichtigungs-Test
 * Erstellt eine Benachrichtigung direkt ohne Abhängigkeiten
 */

session_start();

// Überprüfe Authentifizierung
if (!isset($_SESSION['user_id'])) {
    die('Nicht angemeldet!');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unbekannt';

// Lade die Funktionen
require_once 'includes/functions.php';

// Verzeichnis erstellen
$notificationsDir = 'data/';
$notificationsFile = $notificationsDir . 'notifications.json';

// Stelle sicher, dass Verzeichnis existiert
if (!is_dir($notificationsDir)) {
    mkdir($notificationsDir, 0755, true);
}

// Lese aktuelle Benachrichtigungen
$notifications = [];
if (file_exists($notificationsFile)) {
    $content = file_get_contents($notificationsFile);
    if ($content) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $notifications = $decoded;
        }
    }
}

// Erstelle eine neue Benachrichtigung
$newNotification = [
    'id' => 'test-' . time() . '-' . rand(1000, 9999),
    'user_id' => $user_id,
    'type' => 'test',
    'title' => 'Test-Benachrichtigung',
    'message' => 'Dies ist eine Test-Benachrichtigung um zu prüfen, ob das System funktioniert',
    'link' => 'dashboard.php',
    'related_id' => 'test-' . time(),
    'is_read' => false,
    'created_at' => date('Y-m-d H:i:s')
];

// Füge am Anfang ein (neueste oben)
array_unshift($notifications, $newNotification);

// Speichere
$encoded = json_encode($notifications, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$saveResult = file_put_contents($notificationsFile, $encoded);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Direkte Benachrichtigungs-Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🧪 Direkte Benachrichtigungs-Test</h1>
        
        <div class="alert alert-info">
            <strong>Benutzer ID:</strong> <?php echo htmlspecialchars($user_id); ?><br>
            <strong>Benutzername:</strong> <?php echo htmlspecialchars($username); ?>
        </div>

        <?php if ($saveResult !== false): ?>
            <div class="alert alert-success">
                <h4>✅ Benachrichtigung erfolgreich erstellt!</h4>
                <p><strong>Datei:</strong> <?php echo htmlspecialchars($notificationsFile); ?></p>
                <p><strong>Größe:</strong> <?php echo filesize($notificationsFile); ?> bytes</p>
                <p><strong>Neue Benachrichtigung:</strong></p>
                <pre><?php echo json_encode($newNotification, JSON_PRETTY_PRINT); ?></pre>
                
                <hr>
                
                <h5>Nächste Schritte:</h5>
                <ol>
                    <li>Öffne <a href="dashboard.php" target="_blank">Dashboard</a> (Ctrl+F5 um Cache zu leeren)</li>
                    <li>Schaue oben links auf das Glocken-Icon</li>
                    <li>Überprüfe die Sidebar auf der linken Seite - sollte eine Benachrichtigungs-Badge zeigen</li>
                    <li>Klick auf "Benachrichtigungen" im Widget um alle zu sehen</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <h4>❌ Fehler beim Erstellen der Benachrichtigung</h4>
                <p><strong>Datei:</strong> <?php echo htmlspecialchars($notificationsFile); ?></p>
                <p><strong>Problem:</strong> file_put_contents() fehlgeschlagen</p>
                
                <hr>
                
                <h5>Debugging-Informationen:</h5>
                <ul>
                    <li><strong>Verzeichnis existiert:</strong> <?php echo is_dir($notificationsDir) ? 'Ja' : 'Nein'; ?></li>
                    <li><strong>Verzeichnis beschreibbar:</strong> <?php echo is_writable($notificationsDir) ? 'Ja' : 'Nein'; ?></li>
                    <li><strong>Datei existiert:</strong> <?php echo file_exists($notificationsFile) ? 'Ja' : 'Nein'; ?></li>
                    <li><strong>Datei beschreibbar:</strong> <?php echo file_exists($notificationsFile) && is_writable($notificationsFile) ? 'Ja' : 'Nein'; ?></li>
                </ul>
            </div>
        <?php endif; ?>

        <hr>

        <h5>Aktuelle Benachrichtigungen in der Datei:</h5>
        <pre><?php echo json_encode($notifications, JSON_PRETTY_PRINT); ?></pre>

        <hr>

        <div class="alert alert-warning">
            <strong>📝 Hinweis:</strong> Wenn du das später wieder testen möchtest, klick hier:
            <button class="btn btn-sm btn-warning" onclick="location.reload()">Seite neu laden</button>
        </div>
    </div>
</body>
</html>
