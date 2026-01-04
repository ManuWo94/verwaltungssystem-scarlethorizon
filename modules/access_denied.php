<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Header information
$pageTitle = "Zugriff verweigert";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Zugriff verweigert</h1>
            </div>

            <div class="alert alert-danger">
                <h4><i class="fa fa-exclamation-triangle mr-2"></i> Zugriff verweigert</h4>
                <p>Sie haben keine Berechtigung, auf diese Seite oder Funktion zuzugreifen.</p>
                <p>Wenn Sie glauben, dass dies ein Fehler ist, wenden Sie sich bitte an einen Administrator.</p>
            </div>

            <div class="mt-4">
                <a href="../dashboard.php" class="btn btn-primary">Zurück zum Dashboard</a>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>