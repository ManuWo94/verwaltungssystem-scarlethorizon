<?php
/**
 * Überprüfe Sidebar-Benachrichtigungen
 */

session_start();
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

if (!isset($_SESSION['user_id'])) {
    die('Nicht angemeldet!');
}

$user_id = $_SESSION['user_id'];

// Teste die Funktionen
$stats = [
    'user_id' => $user_id,
    'total_unread' => countUnreadNotifications($user_id),
    'task_unread' => countUnreadNotifications($user_id, 'task'),
    'note_unread' => countUnreadNotifications($user_id, 'public_note_comment'),
    'case_unread' => countUnreadNotifications($user_id, 'case'),
    'all_notifications' => getUserNotifications($user_id, false, 100),
    'notifications_file_exists' => file_exists('data/notifications.json'),
    'notifications_file_size' => filesize('data/notifications.json')
];

// Hole die Raw-Datei
$raw_file = file_get_contents('data/notifications.json');
$raw_json = json_decode($raw_file, true);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sidebar-Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f5f5f5; }
        .container { margin-top: 20px; }
        pre { background-color: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .stat-box { background: white; padding: 15px; margin: 10px 0; border-radius: 4px; border-left: 4px solid #007bff; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Sidebar Benachrichtigungs-Debug</h1>

        <div class="row mt-4">
            <div class="col-md-6">
                <h3>📊 Statistiken</h3>
                
                <div class="stat-box">
                    <div class="text-muted">Benutzer-ID</div>
                    <div class="stat-number"><?php echo htmlspecialchars($user_id); ?></div>
                </div>

                <div class="stat-box">
                    <div class="text-muted">Gesamt ungelesene Benachrichtigungen</div>
                    <div class="stat-number"><?php echo $stats['total_unread']; ?></div>
                </div>

                <div class="stat-box">
                    <div class="text-muted">Aufgaben-Benachrichtigungen</div>
                    <div class="stat-number text-warning"><?php echo $stats['task_unread']; ?></div>
                </div>

                <div class="stat-box">
                    <div class="text-muted">Notiz-Benachrichtigungen</div>
                    <div class="stat-number text-info"><?php echo $stats['note_unread']; ?></div>
                </div>

                <div class="stat-box">
                    <div class="text-muted">Fall-Benachrichtigungen</div>
                    <div class="stat-number text-primary"><?php echo $stats['case_unread']; ?></div>
                </div>

                <div class="stat-box">
                    <div class="text-muted">Datei existiert</div>
                    <div class="stat-number"><?php echo $stats['notifications_file_exists'] ? '✅' : '❌'; ?></div>
                </div>

                <div class="stat-box">
                    <div class="text-muted">Datei-Größe</div>
                    <div class="stat-number"><?php echo $stats['notifications_file_size']; ?> bytes</div>
                </div>
            </div>

            <div class="col-md-6">
                <h3>🧪 Weitere Aktionen</h3>
                
                <a href="test_direct_notification.php" class="btn btn-primary btn-block mb-2">
                    🔔 Direkte Test-Benachrichtigung erstellen
                </a>
                
                <a href="test_notifications.php" class="btn btn-success btn-block mb-2">
                    ✨ Test-Benachrichtigungen-Tool
                </a>
                
                <a href="debug_notifications.php" class="btn btn-info btn-block mb-2">
                    🔧 Vollständiger Debug
                </a>

                <a href="dashboard.php" class="btn btn-warning btn-block">
                    🏠 Zum Dashboard (F5 zum Aktualisieren!)
                </a>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <h3>📋 Rohes JSON-Format</h3>
                <pre><?php echo json_encode($raw_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]'; ?></pre>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <h3>🔍 Detaillierte Informationen</h3>
                <pre><?php echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?></pre>
            </div>
        </div>
    </div>
</body>
</html>
