<?php
// Umleitung für Gerichtsverhandlung terminieren
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

// Nur Richter und Administratoren dürfen Termine festlegen
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

// Standarddatum für Verhandlung (eine Woche in der Zukunft)
$nextWeekDate = date('Y-m-d', strtotime('+7 days'));

// Hole Falldetails
$caseData = null;
if (!empty($indictment['case_id'])) {
    $caseData = findById('cases.json', $indictment['case_id']);
}

// Seitentitel und Header
$pageTitle = 'Gerichtsverhandlung terminieren';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gerichtsverhandlung terminieren</h1>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Verhandlungstermin festlegen</h4>
                        </div>
                        <div class="card-body">
                            <?php if ($indictment['status'] !== 'accepted'): ?>
                                <div class="alert alert-warning">
                                    Diese Klageschrift kann im aktuellen Status nicht terminiert werden.
                                </div>
                                <a href="indictments.php?id=<?php echo $indictmentId; ?>&view=detail" class="btn btn-primary">
                                    Zurück zur Klageschrift
                                </a>
                            <?php else: ?>
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
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Terminplanungsformular -->
                                <form method="post" action="indictments.php" class="needs-validation" novalidate>
                                    <input type="hidden" name="action" value="schedule_court_date">
                                    <input type="hidden" name="indictment_id" value="<?php echo $indictmentId; ?>">
                                    
                                    <div class="form-group">
                                        <label for="trial_date"><strong>Datum der Verhandlung</strong></label>
                                        <input type="date" class="form-control" id="trial_date" name="trial_date" 
                                            value="<?php echo $nextWeekDate; ?>" required>
                                        <small class="form-text text-muted">Bitte wählen Sie das Datum der Gerichtsverhandlung.</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="trial_time"><strong>Uhrzeit der Verhandlung</strong></label>
                                        <input type="time" class="form-control" id="trial_time" name="trial_time" 
                                            value="10:00" required>
                                        <small class="form-text text-muted">Bitte geben Sie die Uhrzeit der Gerichtsverhandlung an.</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="trial_notes"><strong>Anmerkungen zur Verhandlung</strong></label>
                                        <textarea class="form-control" id="trial_notes" name="trial_notes" rows="3"></textarea>
                                        <small class="form-text text-muted">Optionale Anmerkungen zur geplanten Verhandlung.</small>
                                    </div>
                                    
                                    <div class="form-group mt-4">
                                        <a href="indictments.php?id=<?php echo $indictmentId; ?>&view=detail" class="btn btn-secondary">Abbrechen</a>
                                        <button type="submit" class="btn btn-primary">Termin festlegen</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>