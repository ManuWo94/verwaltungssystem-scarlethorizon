<?php
/**
 * Test-Script für Benachrichtigungen
 * Erstellt Test-Benachrichtigungen, um das System zu demonstrieren
 */

session_start();
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Wenn Form abgeschickt wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_test_notification'])) {
    $type = $_POST['type'] ?? 'task';
    
    $titles = [
        'task' => 'Test: Neue Aufgabe zugewiesen',
        'public_note_comment' => 'Test: Neuer Kommentar zu Notiz',
        'case' => 'Test: Neuer Fall zugewiesen'
    ];
    
    $messages = [
        'task' => 'Dies ist eine Test-Benachrichtigung für eine neue Aufgabe.',
        'public_note_comment' => 'Dies ist eine Test-Benachrichtigung für einen neuen Kommentar.',
        'case' => 'Dies ist eine Test-Benachrichtigung für einen neuen Fall.'
    ];
    
    $links = [
        'task' => 'modules/task_assignments.php',
        'public_note_comment' => 'modules/public_notes.php',
        'case' => 'modules/cases.php'
    ];
    
    if (createNotification(
        $user_id,
        $type,
        $titles[$type],
        $messages[$type],
        $links[$type],
        'test-' . time()
    )) {
        $message = 'Test-Benachrichtigung wurde erstellt! Schauen Sie auf das Dashboard oder in die Sidebar.';
    } else {
        $message = 'Fehler beim Erstellen der Test-Benachrichtigung.';
    }
}

// Alle Benachrichtigungen löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_all'])) {
    $notificationsFile = 'data/notifications.json';
    if (file_put_contents($notificationsFile, json_encode([], JSON_PRETTY_PRINT))) {
        $message = 'Alle Benachrichtigungen wurden gelöscht.';
    }
}

// Aktuelle Statistiken
$stats = [
    'total' => countUnreadNotifications($user_id),
    'tasks' => countUnreadNotifications($user_id, 'task'),
    'public_notes' => countUnreadNotifications($user_id, 'public_note_comment'),
    'cases' => countUnreadNotifications($user_id, 'case')
];

$allNotifications = getUserNotifications($user_id, false, 100);
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">🔔 Benachrichtigungs-Test</h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Benachrichtigungs-Statistiken</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="display-4"><?php echo $stats['total']; ?></h3>
                                        <p class="text-muted">Gesamt ungelesen</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="display-4 text-warning"><?php echo $stats['tasks']; ?></h3>
                                        <p class="text-muted">Aufgaben</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="display-4 text-info"><?php echo $stats['public_notes']; ?></h3>
                                        <p class="text-muted">Öffentliche Notizen</p>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center">
                                        <h3 class="display-4 text-primary"><?php echo $stats['cases']; ?></h3>
                                        <p class="text-muted">Fälle</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Test-Benachrichtigungen erstellen</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Erstellen Sie Test-Benachrichtigungen, um das System zu testen:</p>
                            
                            <form method="post" class="mb-3">
                                <input type="hidden" name="create_test_notification" value="1">
                                <input type="hidden" name="type" value="task">
                                <button type="submit" class="btn btn-warning btn-block">
                                    <i class="fas fa-tasks"></i> Aufgaben-Benachrichtigung erstellen
                                </button>
                            </form>
                            
                            <form method="post" class="mb-3">
                                <input type="hidden" name="create_test_notification" value="1">
                                <input type="hidden" name="type" value="public_note_comment">
                                <button type="submit" class="btn btn-info btn-block">
                                    <i class="fas fa-comment"></i> Notiz-Kommentar-Benachrichtigung erstellen
                                </button>
                            </form>
                            
                            <form method="post" class="mb-3">
                                <input type="hidden" name="create_test_notification" value="1">
                                <input type="hidden" name="type" value="case">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-folder"></i> Fall-Benachrichtigung erstellen
                                </button>
                            </form>
                            
                            <hr>
                            
                            <form method="post">
                                <input type="hidden" name="clear_all" value="1">
                                <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('Wirklich alle Benachrichtigungen löschen?')">
                                    <i class="fas fa-trash"></i> Alle Benachrichtigungen löschen
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Anleitung</h5>
                        </div>
                        <div class="card-body">
                            <h6>So funktioniert das Benachrichtigungssystem:</h6>
                            <ol>
                                <li><strong>Sidebar-Badges:</strong> Zahlen erscheinen neben den Menüpunkten (wie bei Apps)</li>
                                <li><strong>Dashboard:</strong> Alle ungelesenen Benachrichtigungen werden auf dem Dashboard angezeigt</li>
                                <li><strong>Automatisch:</strong> Benachrichtigungen werden automatisch erstellt bei:
                                    <ul>
                                        <li>Neuen Aufgaben-Zuweisungen</li>
                                        <li>Aufgaben-Weiterleitungen</li>
                                        <li>Kommentaren zu Aufgaben</li>
                                        <li>Kommentaren zu öffentlichen Notizen</li>
                                    </ul>
                                </li>
                                <li><strong>Verschwinden:</strong> Klicken Sie auf eine Benachrichtigung → Sie wird als gelesen markiert und verschwindet</li>
                                <li><strong>Links:</strong> Jede Benachrichtigung hat einen Link zum betroffenen Element</li>
                            </ol>
                            
                            <div class="alert alert-warning mt-3">
                                <strong>Tipp:</strong> Schauen Sie in die Sidebar links - dort sollten die Zahlen erscheinen!
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (count($allNotifications) > 0): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Alle Benachrichtigungen (<?php echo count($allNotifications); ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Typ</th>
                                            <th>Titel</th>
                                            <th>Nachricht</th>
                                            <th>Erstellt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($allNotifications as $notification): ?>
                                            <tr class="<?php echo $notification['is_read'] ? 'text-muted' : 'font-weight-bold'; ?>">
                                                <td>
                                                    <?php if ($notification['is_read']): ?>
                                                        <span class="badge badge-secondary">Gelesen</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Neu</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $typeLabels = [
                                                        'task' => '<span class="badge badge-warning">Aufgabe</span>',
                                                        'public_note_comment' => '<span class="badge badge-info">Notiz</span>',
                                                        'case' => '<span class="badge badge-primary">Fall</span>'
                                                    ];
                                                    echo $typeLabels[$notification['type']] ?? '<span class="badge badge-secondary">Andere</span>';
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($notification['title']); ?></td>
                                                <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                                <td><?php echo timeAgo($notification['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
