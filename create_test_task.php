<?php
/**
 * Erstelle eine Test-Aufgabe
 * Damit werden auch automatisch Benachrichtigungen ausgelöst
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Nicht angemeldet!');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unbekannt';

require_once 'includes/functions.php';

// Hole alle Benutzer um einen Empfänger zu haben
$usersFile = 'data/users.json';
$users = [];
if (file_exists($usersFile)) {
    $content = file_get_contents($usersFile);
    if ($content) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $users = $decoded;
        }
    }
}

// Filter: Nur andere Benutzer
$otherUsers = array_filter($users, function($u) use ($user_id) {
    return isset($u['id']) && $u['id'] !== $user_id;
});

// Stelle sicher, dass das tasks-Verzeichnis existiert
$tasksDir = 'data/tasks/';
if (!is_dir($tasksDir)) {
    mkdir($tasksDir, 0755, true);
}

$tasksFile = $tasksDir . 'assigned_tasks.json';
$categoriesFile = $tasksDir . 'task_categories.json';

// Lese existierende Tasks
$tasks = [];
if (file_exists($tasksFile)) {
    $content = file_get_contents($tasksFile);
    if ($content) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $tasks = $decoded;
        }
    }
}

// Lese Kategorien
$categories = [];
if (file_exists($categoriesFile)) {
    $content = file_get_contents($categoriesFile);
    if ($content) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $categories = $decoded;
        }
    }
}

// Stelle sicher dass default Kategorien existieren
if (empty($categories)) {
    $categories = [
        ['id' => 'urgent', 'name' => 'Dringend', 'color' => '#dc3545'],
        ['id' => 'normal', 'name' => 'Normal', 'color' => '#007bff'],
        ['id' => 'low', 'name' => 'Niedrig', 'color' => '#28a745']
    ];
    file_put_contents($categoriesFile, json_encode($categories, JSON_PRETTY_PRINT));
}

// Wähle einen zufälligen anderen Benutzer als Empfänger
$assignedTo = null;
if (!empty($otherUsers)) {
    $randomUser = array_rand($otherUsers);
    $assignedTo = $otherUsers[$randomUser]['id'] ?? null;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'normal');
    $assignedToUser = trim($_POST['assigned_to'] ?? $assignedTo ?? '');
    
    if (empty($title)) {
        $error = 'Titel ist erforderlich!';
    } elseif (empty($assignedToUser)) {
        $error = 'Kein Benutzer zum Zuweisen vorhanden!';
    } else {
        // Erstelle die Aufgabe
        $newTask = [
            'id' => 'task-' . time() . '-' . rand(1000, 9999),
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'created_by' => $user_id,
            'assigned_to' => $assignedToUser,
            'status' => 'open',
            'priority' => 'normal',
            'created_at' => date('Y-m-d H:i:s'),
            'due_date' => '',
            'completed_at' => null,
            'comments' => []
        ];
        
        array_unshift($tasks, $newTask);
        
        // Speichere
        $saveResult = file_put_contents($tasksFile, json_encode($tasks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        
        if ($saveResult !== false) {
            // Versuche, eine Benachrichtigung zu erstellen
            require_once 'includes/notifications.php';
            
            try {
                $creatorName = $_SESSION['first_name'] ?? $username;
                if (isset($_SESSION['last_name'])) {
                    $creatorName .= ' ' . $_SESSION['last_name'];
                }
                
                $notifResult = createNotification(
                    $assignedToUser,
                    'task',
                    'Neue Aufgabe zugewiesen',
                    $creatorName . ' hat Ihnen die Aufgabe "' . $title . '" zugewiesen.',
                    'modules/task_assignments.php',
                    $newTask['id']
                );
                
                $message = '✅ Aufgabe erfolgreich erstellt! ';
                if ($notifResult) {
                    $message .= 'Eine Benachrichtigung wurde an den Empfänger gesendet.';
                } else {
                    $message .= '(Benachrichtigung konnte nicht erstellt werden)';
                }
            } catch (Exception $e) {
                $message = '✅ Aufgabe erstellt! (Benachrichtigung-Fehler: ' . $e->getMessage() . ')';
            }
        } else {
            $error = 'Fehler beim Speichern der Aufgabe!';
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test-Aufgabe erstellen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container mt-4" style="max-width: 600px;">
        <h1>📋 Test-Aufgabe erstellen</h1>
        
        <div class="alert alert-info">
            <strong>Dein Benutzer:</strong> <?php echo htmlspecialchars($user_id); ?> (<?php echo htmlspecialchars($username); ?>)
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo $message; ?><br>
                <hr>
                <a href="dashboard.php" class="btn btn-sm btn-primary">Zum Dashboard</a>
                <a href="modules/task_assignments.php" class="btn btn-sm btn-secondary">Zu Aufgabenverteilung</a>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="card p-4">
            <div class="form-group">
                <label for="title"><strong>Aufgaben-Titel</strong></label>
                <input type="text" class="form-control" id="title" name="title" placeholder="z.B. Bericht erstellen" required>
            </div>

            <div class="form-group">
                <label for="description"><strong>Beschreibung</strong></label>
                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Details zur Aufgabe..."></textarea>
            </div>

            <div class="form-group">
                <label for="category"><strong>Kategorie</strong></label>
                <select class="form-control" id="category" name="category">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($otherUsers)): ?>
                <div class="form-group">
                    <label for="assigned_to"><strong>Zugewiesen an</strong></label>
                    <select class="form-control" id="assigned_to" name="assigned_to" required>
                        <option value="">-- Benutzer wählen --</option>
                        <?php foreach ($otherUsers as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['id']); ?>" <?php echo $u['id'] === $assignedTo ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['username'] ?? $u['id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    ⚠️ Es gibt keine anderen Benutzer zum Zuweisen!<br>
                    Erstelle zuerst einen anderen Benutzer im Admin-Bereich.
                </div>
            <?php endif; ?>

            <button type="submit" name="create" class="btn btn-primary btn-block" <?php echo empty($otherUsers) ? 'disabled' : ''; ?>>
                Aufgabe erstellen
            </button>
        </form>

        <hr>

        <div class="alert alert-info mt-4">
            <strong>💡 Was passiert:</strong>
            <ol>
                <li>Diese Aufgabe wird in <code>data/tasks/assigned_tasks.json</code> gespeichert</li>
                <li>Automatisch wird eine Benachrichtigung in <code>data/notifications.json</code> erstellt</li>
                <li>Der Empfänger sollte eine Badge in der Sidebar sehen</li>
                <li>Im Dashboard sollte die Benachrichtigung angezeigt werden</li>
            </ol>
        </div>
    </div>
</body>
</html>
