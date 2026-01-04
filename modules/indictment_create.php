<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Zugriffskontrolle - Verwende die checkUserHasRoleType Funktion für konsistente Rollenüberprüfung
$userRole = $_SESSION['role'];
// Diese Funktion berücksichtigt alle Rollen der Staatsanwaltschaft, einschließlich Senior Prosecutor
$isProsecutor = checkUserHasRoleType($userRole, 'prosecutor');
// Diese Funktion berücksichtigt alle Leitungsrollen
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
        $error = "Bitte geben Sie den Inhalt der Klageschrift ein.";
    } else {
        $content = $_POST['content'];
        $charges = isset($_POST['charges']) ? $_POST['charges'] : '';
        
        // Erstelle neue Klageschrift
        $indictment = [
            'id' => uniqid(),
            'case_id' => $case_id,
            'content' => $content,
            'charges' => $charges,
            'status' => 'pending',
            'date_created' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['username'],
            'last_modified' => date('Y-m-d H:i:s'),
            'last_modified_by' => $_SESSION['username']
        ];
        
        // Speichern der Klageschrift
        $indictments = loadJsonData('indictments.json');
        $indictments[] = $indictment;
        saveJsonData('indictments.json', $indictments);
        
        // Aktualisiere den Status des Falls auf "Klageschrift eingereicht"
        $case['status'] = 'pending';
        $case['last_modified'] = date('Y-m-d H:i:s');
        $case['last_modified_by'] = $_SESSION['username'];
        
        // Speichern des aktualisierten Falls
        updateJsonRecord('cases.json', $case_id, $case);
        
        $message = "Klageschrift erfolgreich eingereicht.";
        
        // Weiterleitung zur Fallübersicht
        header("Location: case_view.php?id=" . $case_id . "&message=" . urlencode($message));
        exit;
    }
}

// Lade alle Straftaten für die Dropdown-Liste
$offenses = [
    "Mord",
    "Totschlag",
    "Körperverletzung",
    "Schwere Körperverletzung",
    "Raub",
    "Diebstahl",
    "Schwerer Diebstahl",
    "Betrug",
    "Urkundenfälschung",
    "Steuerhinterziehung",
    "Verkehrsdelikte",
    "Drogendelikte",
    "Umweltdelikte",
    "Cyberkriminalität",
    "Andere"
];

$pageTitle = "Klageschrift einreichen";
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Klageschrift einreichen</h1>
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
                                    <p><strong>Ursprüngliche Anklage:</strong> <?php echo htmlspecialchars($case['charge']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="charges"><strong>Anklagepunkte</strong> (mehrere auswählen mit Strg/Cmd-Taste)</label>
                            <select multiple class="form-control" id="charges" name="charges[]">
                                <?php foreach ($offenses as $offense): ?>
                                    <option value="<?php echo htmlspecialchars($offense); ?>"><?php echo htmlspecialchars($offense); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Wählen Sie die zutreffenden Anklagepunkte aus.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="content"><strong>Inhalt der Klageschrift</strong></label>
                            <textarea class="form-control" id="content" name="content" rows="15" required></textarea>
                            <small class="form-text text-muted">
                                Geben Sie hier den vollständigen Text der Klageschrift ein. Die erste Zeile wird als Titel verwendet.
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Klageschrift einreichen</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>