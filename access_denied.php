<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Wenn die Seite mit dem Parameter 'reason=inactive' aufgerufen wird, 
// handelt es sich um einen inaktiven oder gelöschten Benutzer.
// In diesem Fall ist der Benutzer zwar bereits in header.php abgemeldet worden,
// wir rufen die Logout-Funktion hier aber nochmals aus Sicherheitsgründen auf.
if (isset($_GET['reason']) && $_GET['reason'] === 'inactive') {
    if (function_exists('logoutUser')) {
        logoutUser();
    } else {
        // Fallback, falls die Funktion nicht existiert
        session_unset();
        session_destroy();
    }
}

// Header information
$pageTitle = "Zugriff verweigert";
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Zugriff verweigert</h1>
            </div>

            <div class="alert alert-danger">
                <h4><i class="fa fa-exclamation-triangle mr-2"></i> Zugriff verweigert</h4>
                <?php if (isset($_GET['reason']) && $_GET['reason'] === 'inactive'): ?>
                    <p>Ihr Benutzerkonto wurde deaktiviert oder gesperrt. Sie können sich nicht mehr anmelden.</p>
                    <p>Bitte kontaktieren Sie einen Administrator, um Ihr Konto wieder zu aktivieren.</p>
                <?php else: ?>
                    <p>Sie haben keine Berechtigung, auf diese Seite oder Funktion zuzugreifen.</p>
                    <p>Wenn Sie glauben, dass dies ein Fehler ist, wenden Sie sich bitte an einen Administrator.</p>
                <?php endif; ?>
            </div>

            <div class="mt-4">
                <?php if (isset($_GET['reason']) && $_GET['reason'] === 'inactive'): ?>
                    <a href="login.php" class="btn btn-primary">Zur Anmeldeseite</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-primary">Zurück zum Dashboard</a>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>