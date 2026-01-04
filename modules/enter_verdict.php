<?php
// Umleitung für Urteil eintragen
session_start();

// Stelle sicher, dass ein Benutzer angemeldet ist
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Nicht angemeldet, Umleitung zur Login-Seite
    header('Location: ../login.php');
    exit;
}

require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Hole Benutzerinformationen
$username = $_SESSION['username'] ?? 'Unknown';
$user_id = $_SESSION['user_id'] ?? '';
$role = $_SESSION['role'] ?? '';

// Nur Richter und Administratoren dürfen Urteile eintragen
if ($role !== 'Richter' && $role !== 'Administrator') {
    // Keine Berechtigung
    header('Location: indictments.php?error=no_permission');
    exit;
}

// Stelle sicher, dass eine Klageschrift-ID vorhanden ist
if (!isset($_GET['id'])) {
    // Keine ID angegeben
    header('Location: indictments.php?error=missing_id');
    exit;
}

$indictmentId = $_GET['id'];
$indictment = findById('indictments.json', $indictmentId);

if (!$indictment) {
    // Klageschrift nicht gefunden
    header('Location: indictments.php?error=invalid_id');
    exit;
}

// Stelle sicher, dass die Klageschrift im richtigen Status ist
if ($indictment['status'] !== 'scheduled') {
    // Falscher Status
    header('Location: indictments.php?id=' . $indictmentId . '&view=detail&error=invalid_status');
    exit;
}

// Heutiges Datum für Urteil
$todayDate = date('Y-m-d');

// Hole Falldetails
$caseData = null;
if (!empty($indictment['case_id'])) {
    $caseData = findById('cases.json', $indictment['case_id']);
}

// Seitentitel und Header
$pageTitle = 'Urteil eintragen';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Urteil eintragen</h1>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">Urteil eingeben</h4>
                        </div>
                        <div class="card-body">
                            <!-- Falldetails -->
                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0">Falldetails</h5>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Aktenzeichen:</strong> <?php echo substr($indictment['case_id'], 0, 8); ?></p>
                                            <p><strong>Angeklagter:</strong> <?php echo htmlspecialchars($caseData['defendant'] ?? 'Nicht angegeben'); ?></p>
                                            <p><strong>Anklage:</strong> <?php echo htmlspecialchars($caseData['charge'] ?? 'Nicht angegeben'); ?></p>
                                            <p><strong>Staatsanwalt:</strong> <?php echo htmlspecialchars($indictment['prosecutor_name'] ?? 'Nicht angegeben'); ?></p>
                                            <p><strong>Verhandlungstermin:</strong> <?php echo formatDate($indictment['trial_date'] ?? '', 'd.m.Y H:i'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Urteilsformular -->
                            <form method="post" action="indictments.php" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="enter_verdict">
                                <input type="hidden" name="indictment_id" value="<?php echo $indictmentId; ?>">
                                
                                <div class="form-group">
                                    <label for="verdict"><strong>Urteilstext</strong></label>
                                    <textarea class="form-control" id="verdict" name="judgment" rows="6" required></textarea>
                                    <small class="form-text text-muted">Bitte geben Sie den vollständigen Urteilstext ein.</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="verdict_date"><strong>Datum des Urteils</strong></label>
                                    <input type="date" class="form-control" id="verdict_date" name="verdict_date" 
                                        value="<?php echo $todayDate; ?>" required>
                                    <small class="form-text text-muted">Standardmäßig auf heute gesetzt.</small>
                                </div>
                                
                                <div class="form-group mt-4">
                                    <a href="indictments.php?id=<?php echo $indictmentId; ?>&view=detail" class="btn btn-secondary">Abbrechen</a>
                                    <button type="submit" class="btn btn-success">Urteil speichern</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>