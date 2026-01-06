<?php
/**
 * Direkter Benachrichtigungs-Test
 * Speichert direkt in notifications.json
 */

session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    die('Nicht angemeldet!');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationsFile = 'data/notifications.json';
    
    // Lade vorhandene Benachrichtigungen
    $notifications = getJsonData($notificationsFile);
    if ($notifications === false) {
        $notifications = [];
    }
    
    // Debug-Info
    error_log("Vor dem Speichern: " . count($notifications) . " Benachrichtigungen");
    
    // Erstelle neue Benachrichtigung
    $notification = [
        'id' => 'test-' . time(),
        'user_id' => $user_id,
        'type' => 'test',
        'title' => 'Direkte Test-Benachrichtigung',
        'message' => 'Dies wurde direkt in der JSON gespeichert',
        'link' => 'test_direct_notification.php',
        'related_id' => 'direct-test',
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Füge hinzu
    array_unshift($notifications, $notification);
    
    // Speichere
    $result = saveJsonData($notificationsFile, $notifications);
    
    if ($result) {
        $success = true;
        $message = '✅ Benachrichtigung wurde erfolgreich gespeichert!';
        
        // Verifiziere das Speichern
        $verify = getJsonData($notificationsFile);
        error_log("Nach dem Speichern: " . count($verify) . " Benachrichtigungen");
    } else {
        $message = '❌ Fehler beim Speichern der Benachrichtigung';
        error_log("Fehler beim Speichern!");
    }
}

// Zeige aktuelle Inhalte
$current = getJsonData('data/notifications.json');
$currentCount = is_array($current) ? count($current) : 0;
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Direkte Benachrichtigungs-Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 600px; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">🔔 Direkter Benachrichtigungs-Test</h4>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>Aktuell gespeicherte Benachrichtigungen:</strong> <span class="badge badge-light"><?php echo $currentCount; ?></span>
                </div>

                <form method="post" class="mb-3">
                    <button type="submit" class="btn btn-primary btn-block" name="create">
                        <i class="fas fa-plus"></i> Test-Benachrichtigung speichern
                    </button>
                </form>

                <hr>

                <h6>📋 Datei-Informationen:</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Benutzer:</td>
                        <td><code><?php echo htmlspecialchars($user_id); ?></code></td>
                    </tr>
                    <tr>
                        <td>Username:</td>
                        <td><code><?php echo htmlspecialchars($username); ?></code></td>
                    </tr>
                    <tr>
                        <td>Datei existiert:</td>
                        <td><?php echo file_exists('data/notifications.json') ? '✅ JA' : '❌ NEIN'; ?></td>
                    </tr>
                    <tr>
                        <td>Datei schreibbar:</td>
                        <td><?php echo is_writable('data/notifications.json') ? '✅ JA' : '❌ NEIN'; ?></td>
                    </tr>
                </table>

                <hr>

                <div class="alert alert-warning">
                    <strong>Nach dem Speichern:</strong>
                    <ol>
                        <li>Gehen Sie zum <a href="dashboard.php">Dashboard</a></li>
                        <li>Aktualisieren Sie die Seite (Ctrl+F5 / Cmd+Shift+R)</li>
                        <li>Schauen Sie ob die Benachrichtigung erscheint</li>
                        <li>Auch in der Sidebar sollte eine Zahl neben "Übersicht" erscheinen</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
