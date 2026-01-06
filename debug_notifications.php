<?php
/**
 * Debug-Script für Benachrichtigungssystem
 */

session_start();
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$results = [];

// Test 1: Datei-Schreibzugriff
$notificationsFile = 'data/notifications.json';
$results['file_exists'] = file_exists($notificationsFile);
$results['file_readable'] = is_readable($notificationsFile);
$results['file_writable'] = is_writable($notificationsFile);

// Test 2: Aktuelle Inhalte
$current = getJsonData($notificationsFile);
$results['current_content'] = $current;
$results['current_count'] = is_array($current) ? count($current) : 'Error';

// Test 3: Test-Benachrichtigung erstellen
$testNotif = createNotification(
    $user_id,
    'test',
    'Debug Test Benachrichtigung',
    'Dies ist eine Debug-Test-Benachrichtigung um zu prüfen ob das System funktioniert',
    'test_notifications.php',
    'debug-test-' . time()
);
$results['create_result'] = $testNotif;

// Test 4: Nach dem Erstellen prüfen
$after = getJsonData($notificationsFile);
$results['after_count'] = is_array($after) ? count($after) : 'Error';
$results['after_content'] = $after;

// Test 5: Ungelesene zählen
$unread = countUnreadNotifications($user_id);
$results['unread_count'] = $unread;

// Test 6: Berechtigungen
exec('ls -la data/notifications.json', $perms);
$results['permissions'] = $perms;

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benachrichtigungs-Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f5f5f5; }
        .debug-box { background-color: #fff; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-pass { color: #28a745; font-weight: bold; }
        .test-fail { color: #dc3545; font-weight: bold; }
        pre { background-color: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">🔧 Benachrichtigungs-Debug</h1>
        
        <div class="debug-box">
            <h3>📋 System-Checks</h3>
            <table class="table">
                <tr>
                    <td>Datei existiert</td>
                    <td class="<?php echo $results['file_exists'] ? 'test-pass' : 'test-fail'; ?>">
                        <?php echo $results['file_exists'] ? '✅ JA' : '❌ NEIN'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Datei lesbar</td>
                    <td class="<?php echo $results['file_readable'] ? 'test-pass' : 'test-fail'; ?>">
                        <?php echo $results['file_readable'] ? '✅ JA' : '❌ NEIN'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Datei schreibbar</td>
                    <td class="<?php echo $results['file_writable'] ? 'test-pass' : 'test-fail'; ?>">
                        <?php echo $results['file_writable'] ? '✅ JA' : '❌ NEIN'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Berechtigungen</td>
                    <td><code><?php echo isset($perms[0]) ? htmlspecialchars($perms[0]) : 'N/A'; ?></code></td>
                </tr>
            </table>
        </div>

        <div class="debug-box">
            <h3>📊 Benachrichtigungs-Statistiken</h3>
            <table class="table">
                <tr>
                    <td>Aktuelle Benachrichtigungen</td>
                    <td><strong><?php echo $results['current_count']; ?></strong></td>
                </tr>
                <tr>
                    <td>Test-Benachrichtigung erstellt</td>
                    <td class="<?php echo $results['create_result'] ? 'test-pass' : 'test-fail'; ?>">
                        <?php echo $results['create_result'] ? '✅ JA' : '❌ NEIN'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Nach dem Erstellen</td>
                    <td><strong><?php echo $results['after_count']; ?></strong></td>
                </tr>
                <tr>
                    <td>Ungelesene Benachrichtigungen</td>
                    <td><strong><?php echo $results['unread_count']; ?></strong></td>
                </tr>
                <tr>
                    <td>Ihr Benutzer-ID</td>
                    <td><code><?php echo htmlspecialchars($user_id); ?></code></td>
                </tr>
            </table>
        </div>

        <div class="debug-box">
            <h3>📄 Datei-Inhalt</h3>
            <h5>Vor Test:</h5>
            <pre><?php echo json_encode($results['current_content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
            
            <h5 class="mt-4">Nach Test:</h5>
            <pre><?php echo json_encode($results['after_content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
        </div>

        <div class="debug-box">
            <h3>🔗 Nächste Schritte</h3>
            <ul>
                <li>Gehen Sie zu <a href="test_notifications.php" class="btn btn-sm btn-primary">test_notifications.php</a> um eine echte Test-Benachrichtigung zu erstellen</li>
                <li>Dann rufen Sie <a href="dashboard.php" class="btn btn-sm btn-info">das Dashboard</a> auf um zu sehen ob die Benachrichtigung angezeigt wird</li>
                <li>Schauen Sie auch in die <strong>Sidebar</strong> links - dort sollte die Zahl 1 neben "Übersicht" erscheinen</li>
            </ul>
        </div>

        <div class="debug-box alert alert-info">
            <strong>💡 Wenn alles grün ist:</strong> Das System funktioniert! Die Benachrichtigungen werden erstellt und gespeichert. 
            Vielleicht fehlen noch automatische Benachrichtigungen bei echten Aktionen (Aufgaben erstellen, Kommentare hinzufügen).
        </div>
    </div>
</body>
</html>
