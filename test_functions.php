<?php
/**
 * Direkter Funktions-Test
 * Überprüft ob die grundlegenden Funktionen funktionieren
 */

session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    die('Nicht angemeldet!');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$testResults = [];

// Test 1: getJsonData funktioniert
$testResults['test1_read'] = [
    'name' => 'Lese notifications.json',
    'result' => getJsonData('data/notifications.json'),
    'success' => true
];

// Test 2: Manuell speichern
$testData = [
    [
        'id' => 'manual-test-' . time(),
        'user_id' => $user_id,
        'type' => 'test',
        'title' => 'Manueller Test',
        'message' => 'Dies wurde manuell gespeichert',
        'link' => '',
        'related_id' => '',
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ]
];

$saveResult = saveJsonData('data/notifications.json', $testData);
$testResults['test2_write'] = [
    'name' => 'Speichere manuell in notifications.json',
    'result' => $saveResult ? 'TRUE' : 'FALSE',
    'success' => $saveResult
];

// Test 3: Verifiziere das Speichern
$verifyRead = getJsonData('data/notifications.json');
$testResults['test3_verify'] = [
    'name' => 'Verifiziere Speichern',
    'count' => is_array($verifyRead) ? count($verifyRead) : 'FEHLER',
    'success' => is_array($verifyRead) && count($verifyRead) > 0
];

// Test 4: Lade notifications.php und teste Funktionen
require_once 'includes/notifications.php';

$testResults['test4_functions'] = [
    'name' => 'notifications.php Funktionen geladen',
    'success' => function_exists('createNotification') && function_exists('countUnreadNotifications')
];

// Test 5: Versuche createNotification
try {
    $createResult = createNotification(
        $user_id,
        'manual_test',
        'Manueller createNotification Test',
        'Dies wurde über createNotification() erstellt',
        'index.php',
        'manual-create-' . time()
    );
    $testResults['test5_create'] = [
        'name' => 'createNotification() aufrufen',
        'result' => $createResult ? 'TRUE' : 'FALSE',
        'success' => $createResult
    ];
} catch (Exception $e) {
    $testResults['test5_create'] = [
        'name' => 'createNotification() aufrufen',
        'error' => $e->getMessage(),
        'success' => false
    ];
}

// Test 6: Zähle ungelesene
try {
    $count = countUnreadNotifications($user_id);
    $testResults['test6_count'] = [
        'name' => 'countUnreadNotifications() aufrufen',
        'result' => $count,
        'success' => is_numeric($count)
    ];
} catch (Exception $e) {
    $testResults['test6_count'] = [
        'name' => 'countUnreadNotifications() aufrufen',
        'error' => $e->getMessage(),
        'success' => false
    ];
}

// Test 7: Finale Überprüfung
$finalRead = getJsonData('data/notifications.json');
$testResults['test7_final'] = [
    'name' => 'Finale Überprüfung notifications.json',
    'count' => is_array($finalRead) ? count($finalRead) : 'FEHLER',
    'size' => filesize('data/notifications.json'),
    'success' => is_array($finalRead) && count($finalRead) > 0
];

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Funktions-Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .test-box { background: white; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .success { border-left: 4px solid #28a745; }
        .failure { border-left: 4px solid #dc3545; }
        .test-title { font-weight: bold; color: #333; }
        .test-content { margin-top: 10px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>🧪 Direkter Funktions-Test</h1>
        
        <div class="alert alert-info">
            <strong>Benutzer:</strong> <?php echo htmlspecialchars($user_id); ?> (<?php echo htmlspecialchars($username); ?>)
        </div>

        <?php foreach ($testResults as $testKey => $test): ?>
            <div class="test-box <?php echo $test['success'] ? 'success' : 'failure'; ?>">
                <div class="test-title">
                    <?php echo $test['success'] ? '✅' : '❌'; ?>
                    <?php echo htmlspecialchars($test['name']); ?>
                </div>
                <div class="test-content">
                    <?php if (isset($test['result'])): ?>
                        <strong>Ergebnis:</strong>
                        <pre><?php echo htmlspecialchars(is_array($test['result']) ? json_encode($test['result'], JSON_PRETTY_PRINT) : (string)$test['result']); ?></pre>
                    <?php endif; ?>
                    
                    <?php if (isset($test['count'])): ?>
                        <strong>Count:</strong> <code><?php echo $test['count']; ?></code>
                    <?php endif; ?>
                    
                    <?php if (isset($test['size'])): ?>
                        <strong>Datei-Größe:</strong> <code><?php echo $test['size']; ?> bytes</code>
                    <?php endif; ?>
                    
                    <?php if (isset($test['error'])): ?>
                        <div class="alert alert-danger mt-2">
                            <strong>Error:</strong> <?php echo htmlspecialchars($test['error']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="alert alert-warning mt-4">
            <strong>Zusammenfassung:</strong><br>
            <?php
            $allPassed = true;
            foreach ($testResults as $test) {
                if (!$test['success']) {
                    $allPassed = false;
                    break;
                }
            }
            
            if ($allPassed) {
                echo "✅ Alle Tests bestanden! Das System funktioniert!<br>";
                echo "Gehen Sie zu <a href='dashboard.php'>dashboard.php</a> und aktualisieren Sie (Ctrl+F5)";
            } else {
                echo "❌ Es gibt Fehler. Überprüfen Sie die roten Test-Boxen oben.";
            }
            ?>
        </div>
    </div>
</body>
</html>
