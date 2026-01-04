<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Zugriffskontrolle - Verwende die checkUserHasRoleType Funktion für konsistente Rollenüberprüfung
$userRole = $_SESSION['role'];
// Diese Funktion berücksichtigt alle Rollen der Staatsanwaltschaft, einschließlich Senior Prosecutor
$isProsecutor = checkUserHasRoleType($userRole, 'prosecutor');
$isLeadership = checkUserHasRoleType($userRole, 'leadership');

// Debug-Info
error_log("Zugriffsprüfung - Rolle: " . $_SESSION['role'] . ", isProsecutor: " . ($isProsecutor ? "true" : "false") . ", isLeadership: " . ($isLeadership ? "true" : "false"));

if (!($isProsecutor || $isLeadership)) {
    header("Location: ../access_denied.php");
    exit;
}

// Überprüfe, ob Fall-ID übergeben wurde
if (!isset($_GET['case_id'])) {
    header("Location: cases.php");
    exit;
}

$case_id = $_GET['case_id'];
$case = findById('cases.json', $case_id);

if (!$case) {
    header("Location: cases.php?error=Fall nicht gefunden");
    exit;
}

$error = '';
$message = '';

// Überprüfen, ob das Formular abgesendet wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['deal_terms'])) {
        $error = "Bitte geben Sie die Bedingungen des Deals ein.";
    } else {
        $deal_terms = $_POST['deal_terms'];
        $reduced_charge = isset($_POST['reduced_charge']) ? $_POST['reduced_charge'] : '';
        
        // Aktualisiere den Fall mit dem Deal
        $case['plea_deal'] = [
            'terms' => $deal_terms,
            'reduced_charge' => $reduced_charge,
            'date_offered' => date('Y-m-d H:i:s'),
            'offered_by' => $_SESSION['username'],
            'status' => 'pending'
        ];
        
        // Stelle sicher, dass der aktuelle Benutzer als Staatsanwalt im Fall eingetragen ist
        $case['prosecutor'] = $_SESSION['username'];
        $case['prosecutor_id'] = $_SESSION['user_id'];
        
        $case['status'] = 'plea_deal_offered';
        $case['last_modified'] = date('Y-m-d H:i:s');
        $case['last_modified_by'] = $_SESSION['username'];
        
        // Speichern des aktualisierten Falls
        updateJsonRecord('cases.json', $case_id, $case);
        
        $message = "Außergerichtlicher Deal erfolgreich angeboten.";
        
        // Weiterleitung zur Fallübersicht
        header("Location: case_view.php?id=" . $case_id . "&message=" . urlencode($message));
        exit;
    }
}

// Reduzierte Anklagen für die Dropdown-Liste
$reducedCharges = [
    "Einfache Körperverletzung statt schwerer Körperverletzung",
    "Fahrlässige Tötung statt Totschlag",
    "Diebstahl statt schwerer Diebstahl",
    "Beihilfe statt Mittäterschaft",
    "Versuch statt Vollendung",
    "Nötigung statt Erpressung",
    "Geldbuße statt Freiheitsstrafe",
    "Bewährungsstrafe statt Haftstrafe",
    "Verringertes Strafmaß",
    "Fallenlassen bestimmter Anklagepunkte",
    "Andere"
];

$pageTitle = "Außergerichtlichen Deal anbieten";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Außergerichtlichen Deal anbieten</h1>
                <div>
                    <a href="case_view.php?id=<?php echo $case_id; ?>" class="btn btn-secondary">
                        <span data-feather="arrow-left"></span> Zurück zum Fall
                    </a>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Fall: <?php echo htmlspecialchars($case['id']); ?> - <?php echo htmlspecialchars($case['defendant']); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Falldetails</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Angeklagter:</strong> <?php echo htmlspecialchars($case['defendant']); ?></p>
                                    <p><strong>Aktueller Status:</strong> <?php echo htmlspecialchars(mapStatusToGerman($case['status'])); ?></p>
                                    <p><strong>Aktuelle Anklage:</strong> <?php echo htmlspecialchars($case['charge']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="reduced_charge"><strong>Reduzierte Anklage</strong></label>
                            <select class="form-control" id="reduced_charge" name="reduced_charge">
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($reducedCharges as $charge): ?>
                                    <option value="<?php echo htmlspecialchars($charge); ?>"><?php echo htmlspecialchars($charge); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Wählen Sie die angebotene reduzierte Anklage aus.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="deal_terms"><strong>Bedingungen des Deals</strong></label>
                            <textarea class="form-control" id="deal_terms" name="deal_terms" rows="15" required></textarea>
                            <small class="form-text text-muted">
                                Beschreiben Sie hier die Bedingungen des außergerichtlichen Deals, einschließlich:
                                <ul>
                                    <li>Vorgeschlagenes Strafmaß</li>
                                    <li>Voraussetzungen für den Deal (z.B. Geständnis)</li>
                                    <li>Zusätzliche Bedingungen (z.B. Gemeinnützige Arbeit)</li>
                                    <li>Zeitliche Vorgaben</li>
                                </ul>
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Deal anbieten</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>