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
    if (empty($_POST['content'])) {
        $error = "Bitte geben Sie den Inhalt des Revisionsantrags ein.";
    } else {
        $content = $_POST['content'];
        $reason = isset($_POST['reason']) ? $_POST['reason'] : '';
        
        // Erstelle neuen Revisionsantrag
        $appeal = [
            'id' => uniqid(),
            'case_id' => $case_id,
            'content' => $content,
            'reason' => $reason,
            'status' => 'pending',
            'date_created' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['username'],
            'last_modified' => date('Y-m-d H:i:s'),
            'last_modified_by' => $_SESSION['username']
        ];
        
        // Speichern des Revisionsantrags
        $appeals = loadJsonData('appeals.json');
        $appeals[] = $appeal;
        saveJsonData('appeals.json', $appeals);
        
        // Aktualisiere den Status des Falls auf "Revision beantragt"
        $case['status'] = 'revision_requested';
        $case['last_modified'] = date('Y-m-d H:i:s');
        $case['last_modified_by'] = $_SESSION['username'];
        
        // Speichern des aktualisierten Falls
        updateJsonRecord('cases.json', $case_id, $case);
        
        $message = "Revisionsantrag erfolgreich eingereicht.";
        
        // Weiterleitung zur Fallübersicht
        header("Location: case_view.php?id=" . $case_id . "&message=" . urlencode($message));
        exit;
    }
}

// Gründe für Revision
$appealReasons = [
    "Verfahrensfehler",
    "Neue Beweise",
    "Fehlurteile des Richters",
    "Unangemessenes Strafmaß",
    "Rechtsfehler",
    "Befangenheit des Gerichts",
    "Unzureichende Verteidigung",
    "Sonstige Gründe"
];

$pageTitle = "Revision beantragen";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Revision beantragen</h1>
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
                                    <p><strong>Anklage:</strong> <?php echo htmlspecialchars($case['charge']); ?></p>
                                    
                                    <?php if (!empty($case['verdict'])): ?>
                                    <p><strong>Urteil vom <?php echo formatDate($case['verdict_date']); ?>:</strong></p>
                                    <div class="alert alert-info">
                                        <?php echo nl2br(htmlspecialchars($case['verdict'])); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="reason"><strong>Revisionsgrund</strong></label>
                            <select class="form-control" id="reason" name="reason">
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($appealReasons as $reason): ?>
                                    <option value="<?php echo htmlspecialchars($reason); ?>"><?php echo htmlspecialchars($reason); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="content"><strong>Inhalt des Revisionsantrags</strong></label>
                            <textarea class="form-control" id="content" name="content" rows="15" required></textarea>
                            <small class="form-text text-muted">
                                Beschreiben Sie hier ausführlich die Gründe für die Revision und die rechtlichen Argumente.
                                Die erste Zeile wird als Titel verwendet.
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Revision beantragen</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>