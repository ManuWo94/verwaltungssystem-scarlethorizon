<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Zeige Erfolgsmeldung bei erfolgreicher Abmeldung
if (isset($_GET['success']) && $_GET['success'] === 'forced_logout') {
    $message = 'Benutzer wurde erfolgreich vom Dienst abgemeldet.';
}

// Handle duty status toggle and administrative actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'force_logout' && isset($_POST['user_id'])) {
        // Überprüfen, ob der Benutzer die Berechtigung hat
        if (!currentUserCan('duty_log', 'force_logout')) {
            $error = 'Sie haben keine Berechtigung, um Benutzer vom Dienst abzumelden.';
        } else {
            $targetUserId = sanitize($_POST['user_id']);
            $reason = sanitize($_POST['reason'] ?? '');
            
            // Zuerst prüfen, ob der Benutzer überhaupt im Dienst ist
            if (!getUserDutyStatus($targetUserId)) {
                $error = 'Der Benutzer ist nicht im Dienst.';
            } else if (forceUserLogout($targetUserId, $user_id, $reason)) {
                $message = 'Benutzer wurde erfolgreich vom Dienst abgemeldet.';
                
                // Nach erfolgreicher Abmeldung die Seite neu laden, um die Benutzerliste zu aktualisieren
                header('Location: duty_log.php?success=forced_logout');
                exit;
            } else {
                $error = 'Fehler beim Abmelden des Benutzers vom Dienst.';
            }
        }
    }
    elseif ($action === 'on_duty' || $action === 'off_duty') {
        $status = $action;
        // Stelle die Zeitzone auf Europe/Berlin für korrekte mitteleuropäische Zeit
        date_default_timezone_set('Europe/Berlin');
        $timestamp = date('Y-m-d H:i:s');
        
        // Prüfen, ob der aktuelle Status bereits dem gewünschten Status entspricht
        $currentStatus = getUserDutyStatus($user_id);
        $wantsOnDuty = ($status === 'on_duty');
        
        // Standort erfassen, falls angegeben
        $location = '';
        if (isset($_POST['location'])) {
            $location = sanitize($_POST['location']);
        }
        
        // Nur fortfahren, wenn sich der Status tatsächlich ändert
        if ($currentStatus !== $wantsOnDuty) {
            $dutyEntry = [
                'id' => generateUniqueId(),
                'user_id' => $user_id,
                'username' => $username,
                'status' => $status,
                'timestamp' => $timestamp
            ];
            
            // Standort hinzufügen, wenn einer angegeben wurde
            if (!empty($location)) {
                $dutyEntry['location'] = $location;
            }
            
            $dutyLog = loadJsonData('duty_log.json');
            $dutyLog[] = $dutyEntry;
            
            if (saveJsonData('duty_log.json', $dutyLog)) {
                $statusText = $status === 'on_duty' ? 'im Dienst' : 'außer Dienst';
                $message = "Sie sind jetzt {$statusText}. Ihr Status wurde aktualisiert.";
            } else {
                $error = 'Fehler beim Aktualisieren des Dienststatus. Bitte versuchen Sie es erneut.';
            }
        } else {
            // Wenn sich nur der Standort ändert, aber der Status gleich bleibt
            if ($wantsOnDuty && !empty($location)) {
                $dutyLog = loadJsonData('duty_log.json');
                
                // Suche den letzten on_duty Eintrag für diesen Benutzer
                $latestEntryIndex = null;
                $latestTimestamp = 0;
                
                foreach ($dutyLog as $index => $entry) {
                    if ($entry['user_id'] === $user_id && $entry['status'] === 'on_duty' && strtotime($entry['timestamp']) > $latestTimestamp) {
                        $latestEntryIndex = $index;
                        $latestTimestamp = strtotime($entry['timestamp']);
                    }
                }
                
                // Wenn ein Eintrag gefunden wurde, aktualisiere seinen Standort
                if ($latestEntryIndex !== null) {
                    $dutyLog[$latestEntryIndex]['location'] = $location;
                    if (saveJsonData('duty_log.json', $dutyLog)) {
                        $message = "Ihr Standort wurde aktualisiert.";
                    } else {
                        $error = 'Fehler beim Aktualisieren des Standorts. Bitte versuchen Sie es erneut.';
                    }
                } else {
                    $statusText = $status === 'on_duty' ? 'im Dienst' : 'außer Dienst';
                    $message = "Sie sind bereits {$statusText}. Keine Änderung vorgenommen.";
                }
            } else {
                $statusText = $status === 'on_duty' ? 'im Dienst' : 'außer Dienst';
                $message = "Sie sind bereits {$statusText}. Keine Änderung vorgenommen.";
            }
        }
    }
}

// Stelle die Zeitzone auf Europe/Berlin für korrekte Anzeige
date_default_timezone_set('Europe/Berlin');

// Duty Log Einträge werden nur noch für den aktuellen Status benötigt
$dutyLog = loadJsonData('duty_log.json');

// Get current duty status
$dutyStatus = getUserDutyStatus($user_id);

// Get all users' current status for view (jetzt für alle Benutzer verfügbar)
$allUsers = [];
$onDutyUsers = []; // Nur Benutzer im Dienst
$users = getAllUsers();

foreach ($users as $user) {
    $status = getUserDutyStatus($user['id']);
    $user['duty_status'] = $status ? 'on_duty' : 'off_duty';
    $allUsers[] = $user;
    
    // Füge den Benutzer zur Liste der Benutzer im Dienst hinzu, wenn er im Dienst ist
    if ($status) {
        $onDutyUsers[] = $user;
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dienstprotokoll</h1>
                <div class="duty-status-indicator">
                    <?php if ($dutyStatus): ?>
                        <span class="badge badge-success">Im Dienst</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Außer Dienst</span>
                    <?php endif; ?>
                    <form method="post" action="duty_log.php" class="d-inline ml-2">
                        <input type="hidden" name="action" value="<?php echo $dutyStatus ? 'off_duty' : 'on_duty'; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $dutyStatus ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                            <?php echo $dutyStatus ? 'Dienst beenden' : 'Dienst beginnen'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($dutyStatus): ?>
                <!-- Standort-Update-Formular, nur wenn der Benutzer im Dienst ist -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Aktuellen Standort angeben</h4>
                            </div>
                            <div class="card-body">
                                <form method="post" action="duty_log.php" class="form-inline">
                                    <input type="hidden" name="action" value="on_duty">
                                    <div class="form-group mr-2 flex-grow-1">
                                        <label for="location" class="sr-only">Standort</label>
                                        <input type="text" class="form-control w-100" id="location" name="location" placeholder="Aktuellen Standort eingeben (z.B. Büro, Patrouille, Gerichtssaal)">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Standort aktualisieren</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Diese Sektion ist jetzt für alle Benutzer sichtbar -->
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4>Mitarbeiter im Dienst</h4>
                            <form method="get" action="duty_log.php">
                                <button type="submit" name="show_all" value="<?php echo isset($_GET['show_all']) ? '0' : '1'; ?>" class="btn btn-sm btn-outline-secondary">
                                    <?php echo isset($_GET['show_all']) ? 'Nur im Dienst anzeigen' : 'Alle anzeigen'; ?>
                                </button>
                            </form>
                        </div>
                        <div class="card-body">
                            <?php 
                            // Zeige entweder alle Benutzer oder nur die im Dienst, basierend auf dem GET-Parameter
                            $usersToShow = isset($_GET['show_all']) && $_GET['show_all'] == '1' ? $allUsers : $onDutyUsers;
                            ?>
                            
                            <?php if (count($usersToShow) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Rolle</th>
                                                <th>Status</th>
                                                <th>Standort</th>
                                                <th>Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($usersToShow as $user): 
                                                // Finde den letzten on_duty Eintrag für diesen Benutzer
                                                $location = '';
                                                if ($user['duty_status'] === 'on_duty') {
                                                    $latestEntry = null;
                                                    $latestTimestamp = 0;
                                                    
                                                    foreach ($dutyLog as $entry) {
                                                        if ($entry['user_id'] === $user['id'] && $entry['status'] === 'on_duty' 
                                                            && strtotime($entry['timestamp']) > $latestTimestamp) {
                                                            $latestEntry = $entry;
                                                            $latestTimestamp = strtotime($entry['timestamp']);
                                                        }
                                                    }
                                                    
                                                    if ($latestEntry && isset($latestEntry['location'])) {
                                                        $location = $latestEntry['location'];
                                                    }
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                                    <td>
                                                        <?php if ($user['duty_status'] === 'on_duty'): ?>
                                                            <span class="badge badge-success">Im Dienst</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Außer Dienst</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo !empty($location) ? htmlspecialchars($location) : ''; ?></td>
                                                    <td>
                                                        <?php if ($user['duty_status'] === 'on_duty' && $user['id'] !== $user_id && currentUserCan('duty_log', 'force_logout')): ?>
                                                            <button type="button" class="btn btn-sm btn-warning logout-user-btn" 
                                                                    data-toggle="modal" 
                                                                    data-target="#logoutUserModal" 
                                                                    data-user-id="<?php echo $user['id']; ?>"
                                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                                <i class="fas fa-sign-out-alt"></i> Abmelden
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">
                                    <?php echo isset($_GET['show_all']) ? 'Keine Benutzer gefunden.' : 'Derzeit sind keine Mitarbeiter im Dienst.'; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal für Benutzerabmeldung -->
<div class="modal fade" id="logoutUserModal" tabindex="-1" role="dialog" aria-labelledby="logoutUserModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutUserModalLabel">Benutzer abmelden</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="force_logout">
                    <input type="hidden" name="user_id" id="logout_user_id">
                    
                    <p>Sind Sie sicher, dass Sie <strong id="logout_username"></strong> vom Dienst abmelden möchten?</p>
                    
                    <div class="form-group">
                        <label for="logout_reason">Grund (optional):</label>
                        <textarea class="form-control" id="logout_reason" name="reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-warning">Benutzer abmelden</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// JavaScript für die Benutzerabmeldung
document.addEventListener('DOMContentLoaded', function() {
    const logoutButtons = document.querySelectorAll('.logout-user-btn');
    
    logoutButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const username = this.getAttribute('data-username');
            
            document.getElementById('logout_user_id').value = userId;
            document.getElementById('logout_username').textContent = username;
        });
    });
});
</script>
