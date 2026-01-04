<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Standardmäßig die Umgebung auf Development setzen
putenv('APP_ENV=development');

// Nur Administratoren können die Datenbank zurücksetzen
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = $isLoggedIn && (
    (isset($_SESSION['role']) && $_SESSION['role'] === 'Administrator') || 
    (isset($_SESSION['roles']) && (in_array('Administrator', $_SESSION['roles']) || in_array('Chief Justice', $_SESSION['roles'])))
);

// Status-Variable
$resetPerformed = false;
$error = '';

// Wenn Reset angefordert wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_confirm']) && $_POST['reset_confirm'] === 'yes') {
    if (!$isAdmin) {
        $error = 'Nur Administratoren können die Datenbank zurücksetzen.';
    } else {
        // Führe das Reset-Skript aus
        ob_start();
        include 'reset_database.php';
        $output = ob_get_clean();
        $resetPerformed = true;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank zurücksetzen | Department of Justice Records Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2>Datenbank zurücksetzen</h2>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Fehler:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($resetPerformed): ?>
                    <div class="alert alert-success">
                        <h4>Datenbank erfolgreich zurückgesetzt</h4>
                        <p>Die Datenbank wurde erfolgreich zurückgesetzt und mit Standarddaten initialisiert.</p>
                        <hr>
                        <h5>Admin-Zugangsdaten:</h5>
                        <ul>
                            <li><strong>Benutzername:</strong> OConnor</li>
                            <li><strong>Passwort:</strong> admin</li>
                        </ul>
                    </div>
                    <pre class="bg-dark text-light p-3"><?php echo htmlspecialchars($output); ?></pre>
                <?php else: ?>
                    <?php if (!$isLoggedIn): ?>
                        <div class="alert alert-warning">
                            <strong>Hinweis:</strong> Sie müssen angemeldet sein, um diese Funktion nutzen zu können.
                        </div>
                    <?php elseif (!$isAdmin): ?>
                        <div class="alert alert-danger">
                            <strong>Zugriff verweigert:</strong> Nur Administratoren können die Datenbank zurücksetzen.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <h4>Warnung!</h4>
                            <p>Das Zurücksetzen der Datenbank löscht alle vorhandenen Daten und ersetzt sie durch Standarddaten.</p>
                            <p>Diese Aktion kann nicht rückgängig gemacht werden. Ein Backup der aktuellen Daten wird erstellt, aber für produktive Umgebungen wird empfohlen, zusätzlich ein manuelles Backup durchzuführen.</p>
                        </div>
                        <form method="post" action="database_reset.php">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="reset_confirm" value="yes" id="resetConfirm">
                                <label class="form-check-label" for="resetConfirm">
                                    Ich verstehe, dass alle vorhandenen Daten gelöscht werden, und bestätige das Zurücksetzen der Datenbank.
                                </label>
                            </div>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie die Datenbank zurücksetzen möchten?');">
                                Datenbank zurücksetzen
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-primary">Zurück zum Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>