<?php
/**
 * Bereinigt doppelte Benutzereinträge in der users.json-Datei
 * Behält nur den neuesten Eintrag für jeden Benutzernamen bei
 */
session_start();
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Administratorzugriff erzwingen
if (!isAdminSession()) {
    header('Location: ../dashboard.php?error=admin_access_denied');
    exit;
}

// Message- und Error-Variablen initialisieren
$message = '';
$error = '';

// Bereinigungsprozess starten, wenn das Formular abgesendet wurde
if (isset($_POST['cleanup'])) {
    // Lade alle Benutzer aus der Datei
    $users = loadJsonData('users.json');
    $originalCount = count($users);
    
    // Temporäres Array zur Verfolgung der neuesten Einträge für jeden Benutzernamen
    $uniqueUsers = [];
    
    // Durchlaufe alle Benutzer und behalte nur den neuesten Eintrag für jeden Benutzernamen
    foreach ($users as $user) {
        $username = $user['username'];
        
        // Wenn wir diesen Benutzernamen noch nicht gesehen haben oder dieser Eintrag neuer ist
        if (!isset($uniqueUsers[$username]) || 
            (isset($user['date_created']) && isset($uniqueUsers[$username]['date_created']) && 
             strtotime($user['date_created']) > strtotime($uniqueUsers[$username]['date_created']))) {
            $uniqueUsers[$username] = $user;
        }
    }
    
    // Konvertiere zurück in ein numerisch indiziertes Array
    $cleanedUsers = array_values($uniqueUsers);
    $newCount = count($cleanedUsers);
    
    // Speichere die bereinigten Daten zurück in die Datei
    if (saveJsonData('users.json', $cleanedUsers)) {
        $removedCount = $originalCount - $newCount;
        $message = "Bereinigung abgeschlossen! $removedCount doppelte Benutzereinträge wurden entfernt.";
    } else {
        $error = "Fehler beim Speichern der bereinigten Benutzerdaten.";
    }
}

// Page title
$pageTitle = "Benutzerbereinigung";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Benutzerbereinigung</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="users.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zurück zur Benutzerverwaltung
                    </a>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Doppelte Benutzereinträge bereinigen</h5>
                    <p>
                        Diese Funktion entfernt doppelte Benutzereinträge aus der users.json-Datei.
                        Es werden nur die neuesten Einträge für jeden Benutzernamen beibehalten.
                    </p>
                    
                    <div class="alert alert-warning">
                        <strong>Warnung:</strong> Diese Aktion kann nicht rückgängig gemacht werden. 
                        Es wird empfohlen, vor der Bereinigung eine Sicherungskopie der Benutzerdaten zu erstellen.
                    </div>
                    
                    <?php
                    // Zeige aktuelle Benutzerstatistiken
                    $users = loadJsonData('users.json');
                    $uniqueUsernames = array_unique(array_column($users, 'username'));
                    $duplicateCount = count($users) - count($uniqueUsernames);
                    ?>
                    
                    <div class="card mb-3">
                        <div class="card-body bg-light">
                            <h6>Aktuelle Benutzerstatistik:</h6>
                            <ul>
                                <li><strong>Gesamtzahl der Benutzereinträge:</strong> <?php echo count($users); ?></li>
                                <li><strong>Eindeutige Benutzernamen:</strong> <?php echo count($uniqueUsernames); ?></li>
                                <li><strong>Doppelte Einträge:</strong> <?php echo $duplicateCount; ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if ($duplicateCount > 0): ?>
                        <form method="post" action="cleanup_users.php" onsubmit="return confirm('Sind Sie sicher, dass Sie die Bereinigung durchführen möchten?');">
                            <button type="submit" name="cleanup" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Doppelte Benutzer bereinigen
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Es wurden keine doppelten Benutzereinträge gefunden.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>