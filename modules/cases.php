<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Check if user is logged in and has permission to view cases
checkPermissionOrDie('cases', 'view');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Helper: find or create defendant by name/tg
function findOrCreateDefendant($name, $tgNumber, $userId) {
    $defendants = loadJsonData('defendants.json');
    $normalizedName = strtolower(trim($name));
    $matchId = null;

    foreach ($defendants as $defendant) {
        $defName = strtolower(trim($defendant['name'] ?? ''));
        $defTg = strtolower(trim($defendant['tg_number'] ?? ''));
        if ($defName === $normalizedName || (!empty($tgNumber) && $defTg === strtolower(trim($tgNumber)))) {
            $matchId = $defendant['id'];
            // optional: backfill TG if missing
            if (empty($defendant['tg_number']) && !empty($tgNumber)) {
                $defendant['tg_number'] = $tgNumber;
                updateRecord('defendants.json', $matchId, $defendant);
            }
            break;
        }
    }

    if (!$matchId) {
        $newDefendant = [
            'id' => generateUniqueId(),
            'name' => $name,
            'tg_number' => $tgNumber,
            'history' => [],
            'created_by' => $userId,
            'date_created' => date('Y-m-d H:i:s')
        ];
        insertRecord('defendants.json', $newDefendant);
        $matchId = $newDefendant['id'];
    }

    return $matchId;
}

// Helper: append history entry for defendant
function appendDefendantHistory($defendantId, $entry) {
    if (!$defendantId) {
        return;
    }
    $defendant = findById('defendants.json', $defendantId);
    if (!$defendant) {
        return;
    }
    if (!isset($defendant['history']) || !is_array($defendant['history'])) {
        $defendant['history'] = [];
    }
    array_unshift($defendant['history'], $entry);
    updateRecord('defendants.json', $defendantId, $defendant);
}

// Handle case actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'delete' && isset($_POST['case_id'])) {
            // Check delete permission
            checkPermissionOrDie('cases', 'delete');
            
            $caseId = $_POST['case_id'];
            
            if (deleteRecord('cases.json', $caseId)) {
                $message = 'Fall wurde erfolgreich gelöscht.';
            } else {
                $error = 'Fehler beim Löschen des Falls.';
            }
        }
    } else {
        // Check create/edit permission
        if (isset($_POST['case_id']) && !empty($_POST['case_id'])) {
            checkPermissionOrDie('cases', 'edit');
        } else {
            checkPermissionOrDie('cases', 'create');
        }
        
        // Handle case creation/edit
        $defendantName = sanitize($_POST['defendant'] ?? '');
        $defendantTg = sanitize($_POST['defendant_tg'] ?? '');
        $limitationId = sanitize($_POST['limitation_id'] ?? '');
        $incidentDate = sanitize($_POST['incident_date'] ?? '');
        $expirationDateInput = sanitize($_POST['expiration_date'] ?? '');
        $limitations = loadJsonData('limitations.json');
        $limitationDays = null;
        foreach ($limitations as $lim) {
            if (($lim['id'] ?? '') === $limitationId) {
                $limitationDays = (int) ($lim['days'] ?? 0);
                break;
            }
        }

        // Compute expiration date if not manually provided
        if (empty($expirationDateInput) && !empty($incidentDate) && $limitationDays) {
            $expirationDateInput = date('Y-m-d', strtotime($incidentDate . " +{$limitationDays} days"));
        }

        // Resolve defendant id (create if needed)
        $defendantId = null;
        if (!empty($defendantName)) {
            $defendantId = findOrCreateDefendant($defendantName, $defendantTg, $user_id);
        }

        $caseData = [
            'defendant_id' => $defendantId,
            'defendant' => $defendantName,
            'defendant_tg' => $defendantTg,
            'charge' => sanitize($_POST['charge'] ?? ''),
            'case_type' => sanitize($_POST['case_type'] ?? 'Straf'),
            'incident_date' => $incidentDate,
            'expiration_date' => $expirationDateInput,
            'limitation_id' => $limitationId,
            'bail_amount' => sanitize($_POST['bail_amount'] ?? ''),
            'district' => sanitize($_POST['district'] ?? ''),
            'prosecutor' => sanitize($_POST['prosecutor'] ?? ''),
            'judge' => sanitize($_POST['judge'] ?? ''),
            'status' => sanitize($_POST['status'] ?? 'Open')
        ];
        
        // Validate required fields
        if (empty($caseData['defendant']) || empty($caseData['charge']) || empty($caseData['incident_date'])) {
            $error = 'Please fill in all required fields.';
        } else {
            if (isset($_POST['case_id']) && !empty($_POST['case_id'])) {
                // Update existing case
                $caseId = $_POST['case_id'];
                
                if (updateRecord('cases.json', $caseId, $caseData)) {
                    $message = 'Fall wurde erfolgreich aktualisiert.';
                } else {
                    $error = 'Fehler beim Aktualisieren des Falls.';
                }
            } else {
                // Create new case
                if (!empty($_POST['custom_id'])) {
                    // Verwende das manuell eingegebene Aktenzeichen
                    $caseData['id'] = sanitize($_POST['custom_id']);
                    
                    // Überprüfe, ob die ID bereits existiert
                    $existingCase = findById('cases.json', $caseData['id']);
                    if ($existingCase) {
                        $error = 'Ein Fall mit diesem Aktenzeichen existiert bereits.';
                    }
                } else {
                    // Generiere eine neue ID, wenn keine angegeben wurde
                    $caseData['id'] = generateUniqueId();
                }
                
                if (empty($error)) {
                    $caseData['created_by'] = $user_id;
                    $caseData['date_created'] = date('Y-m-d H:i:s');
                    
                    if (insertRecord('cases.json', $caseData)) {
                        $message = 'Fall wurde erfolgreich erstellt.';
                        // history entry for defendant
                        appendDefendantHistory($defendantId, [
                            'case_id' => $caseData['id'],
                            'type' => 'charge',
                            'charge' => $caseData['charge'],
                            'status' => $caseData['status'],
                            'date' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $error = 'Fehler beim Erstellen des Falls.';
                    }
                }
            }
        }
    }
}

// Get status filter
$statusFilter = $_GET['status'] ?? '';

// Load cases with optional filtering
$cases = loadJsonData('cases.json');

// Überprüfe jede Fallakte auf Verjährung bevor sie angezeigt wird
foreach ($cases as $key => $case) {
    // Überprüfe den Fall auf Verjährung
    $cases[$key] = checkCaseExpiration($case);
}

if (!empty($statusFilter)) {
    $cases = array_filter($cases, function($case) use ($statusFilter) {
        return strtolower($case['status']) === strtolower($statusFilter);
    });
}

// Sort cases by date (newest first)
usort($cases, function($a, $b) {
    return strtotime($b['date_created'] ?? '0') - strtotime($a['date_created'] ?? '0');
});

// Load defendants for dropdown
$defendants = loadJsonData('defendants.json');

// Load limitations for dropdown
$limitations = loadJsonData('limitations.json');

// Load users for prosecutor/judge dropdowns
$users = getAllUsers();
$judges = array_filter($users, function($user) {
    // Überprüfe sowohl die Hauptrolle als auch zusätzliche Rollen
    if ($user['role'] === 'Judge') {
        return true;
    }
    // Überprüfe auch die roles-Array, wenn es existiert
    if (isset($user['roles']) && is_array($user['roles'])) {
        return in_array('Judge', $user['roles']);
    }
    return false;
});
$prosecutors = array_filter($users, function($user) {
    // Überprüfe sowohl die Hauptrolle als auch zusätzliche Rollen
    if ($user['role'] === 'Prosecutor') {
        return true;
    }
    // Überprüfe auch die roles-Array, wenn es existiert
    if (isset($user['roles']) && is_array($user['roles'])) {
        return in_array('Prosecutor', $user['roles']);
    }
    return false;
});
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 cases-page main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Fallverwaltung</h1>
                <div class="btn-group">
                    <?php if (currentUserCan('cases', 'create')): ?>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#addCaseModal" data-case-type="Straf">
                        <span data-feather="alert-triangle"></span> Strafsache anlegen
                    </button>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addCaseModal" data-case-type="Zivil">
                        <span data-feather="briefcase"></span> Zivilsache anlegen
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Tabs für Straf/Zivil -->
            <ul class="nav nav-tabs mb-3" id="caseTypeTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab">
                        Alle Fälle <span class="badge badge-secondary"><?php echo count($cases); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="straf-tab" data-toggle="tab" href="#straf" role="tab">
                        <span data-feather="alert-triangle"></span> Strafsachen 
                        <span class="badge badge-danger"><?php echo count(array_filter($cases, function($c) { return ($c['case_type'] ?? 'Straf') === 'Straf'; })); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="zivil-tab" data-toggle="tab" href="#zivil" role="tab">
                        <span data-feather="briefcase"></span> Zivilsachen 
                        <span class="badge badge-primary"><?php echo count(array_filter($cases, function($c) { return ($c['case_type'] ?? 'Straf') === 'Zivil'; })); ?></span>
                    </a>
                </li>
            </ul>

            <div class="mb-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <form class="form-inline" id="status-filter-form" method="get" action="cases.php">
                            <label class="mr-2" for="status-filter">Status-Filter:</label>
                            <select class="form-control mr-2" id="status-filter" name="status" onchange="document.getElementById('status-filter-form').submit();">
                                <option value="" <?php echo empty($statusFilter) ? 'selected' : ''; ?>>Alle Fälle</option>
                                <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Offen</option>
                                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Bearbeitung</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Klageschrift eingereicht</option>
                                <option value="accepted" <?php echo $statusFilter === 'accepted' ? 'selected' : ''; ?>>Klage angenommen</option>
                                <option value="scheduled" <?php echo $statusFilter === 'scheduled' ? 'selected' : ''; ?>>Terminiert</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Abgeschlossen</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Abgelehnt</option>
                                <option value="dismissed" <?php echo $statusFilter === 'dismissed' ? 'selected' : ''; ?>>Eingestellt</option>
                                <option value="revision_requested" <?php echo $statusFilter === 'revision_requested' ? 'selected' : ''; ?>>Revision beantragt</option>
                                <option value="revision_in_progress" <?php echo $statusFilter === 'revision_in_progress' ? 'selected' : ''; ?>>Revision in Bearbeitung</option>
                                <option value="revision_completed" <?php echo $statusFilter === 'revision_completed' ? 'selected' : ''; ?>>Revision abgeschlossen</option>
                                <option value="revision_verdict" <?php echo $statusFilter === 'revision_verdict' ? 'selected' : ''; ?>>Revisionsurteil</option>
                            </select>
                        </form>
                    </div>
                    <div class="col-md-6 text-right">
                        <span class="text-muted"><?php echo count($cases); ?> Fälle gefunden</span>
                    </div>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="caseTypeTabContent">
                <!-- Alle Fälle -->
                <div class="tab-pane fade show active" id="all" role="tabpanel">
                    <?php renderCaseTable($cases); ?>
                </div>
                
                <!-- Strafsachen -->
                <div class="tab-pane fade" id="straf" role="tabpanel">
                    <?php 
                    $strafCases = array_filter($cases, function($c) { 
                        return ($c['case_type'] ?? 'Straf') === 'Straf'; 
                    });
                    renderCaseTable($strafCases); 
                    ?>
                </div>
                
                <!-- Zivilsachen -->
                <div class="tab-pane fade" id="zivil" role="tabpanel">
                    <?php 
                    $zivilCases = array_filter($cases, function($c) { 
                        return ($c['case_type'] ?? 'Straf') === 'Zivil'; 
                    });
                    renderCaseTable($zivilCases); 
                    ?>
                </div>
            </div>
        </main>
        </div>
    </div>

<?php
// Funktion zum Rendern der Tabelle
function renderCaseTable($casesToDisplay) {
    global $statusLabels;
?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Aktenzeichen</th>
                                    <th>Typ</th>
                                    <th>Angeklagter</th>
                                    <th>Anklage</th>
                                    <th>Vorfallsdatum</th>
                                    <th>Verjährungsdatum</th>
                                    <th>Bezirk</th>
                                    <th>Staatsanwalt</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($casesToDisplay) > 0): ?>
                                    <?php foreach ($casesToDisplay as $case): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $type = $case['case_type'] ?? 'Straf';
                                                $badgeClass = $type === 'Zivil' ? 'badge-info' : 'badge-danger';
                                                echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($type) . '</span>';
                                                ?>
                                            </td>
                                            <td><a href="case_view.php?id=<?php echo $case['id']; ?>"><?php echo htmlspecialchars('#' . substr($case['id'], 0, 8)); ?></a></td>
                                    <td><?php echo htmlspecialchars($case['defendant']); ?></td>
                                    <td><?php echo htmlspecialchars($case['charge']); ?></td>
                                    <td><?php echo isset($case['incident_date']) ? htmlspecialchars(formatDate($case['incident_date'])) : 'Nicht angegeben'; ?></td>
                                    <td>
                                        <?php 
                                            if (isset($case['expiration_date']) && !empty($case['expiration_date'])) {
                                                echo htmlspecialchars(formatDate($case['expiration_date']));
                                                $expirationDate = strtotime($case['expiration_date']);
                                                $today = time();
                                                $daysRemaining = floor(($expirationDate - $today) / (60 * 60 * 24));
                                                
                                                if ($daysRemaining < 0) {
                                                    echo ' <span class="badge badge-danger">Abgelaufen</span>';
                                                } elseif ($daysRemaining < 7) {
                                                    echo ' <span class="badge badge-warning">' . $daysRemaining . ' Tage übrig</span>';
                                                }
                                            } else {
                                                echo 'Nicht angegeben';
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo isset($case['district']) ? htmlspecialchars($case['district']) : 'Nicht angegeben'; ?></td>
                                    <td><?php echo isset($case['prosecutor']) ? htmlspecialchars($case['prosecutor']) : 'Nicht zugewiesen'; ?></td>
                                    <td>
                                        <?php 
                                            $statusClass = 'secondary';
                                            $status = strtolower($case['status']);
                                            
                                            if ($status === 'open' || $status === 'offen') {
                                                $statusClass = 'info';
                                            } elseif ($status === 'in progress' || $status === 'in bearbeitung') {
                                                $statusClass = 'primary';
                                            } elseif ($status === 'pending trial' || $status === 'anhängiges verfahren') {
                                                $statusClass = 'warning';
                                            } elseif ($status === 'closed' || $status === 'abgeschlossen') {
                                                $statusClass = 'success';
                                            } elseif ($status === 'dismissed' || $status === 'eingestellt') {
                                                $statusClass = 'danger';
                                            } elseif ($status === 'appealed' || $status === 'berufung eingelegt') {
                                                $statusClass = 'dark';
                                            } elseif ($status === 'revision_requested' || $status === 'revision beantragt') {
                                                $statusClass = 'info';
                                            } elseif ($status === 'revision_in_progress' || $status === 'revision in bearbeitung') {
                                                $statusClass = 'primary';
                                            } elseif ($status === 'revision_completed' || $status === 'revision abgeschlossen') {
                                                $statusClass = 'success';
                                            } elseif ($status === 'revision_rejected' || $status === 'revision abgelehnt') {
                                                $statusClass = 'danger';
                                            } elseif ($status === 'revision_verdict' || $status === 'revisionsurteil') {
                                                $statusClass = 'primary';
                                            } elseif ($status === 'klageschrift eingereicht') {
                                                $statusClass = 'info';
                                            } elseif ($status === 'klage angenommen') {
                                                $statusClass = 'primary';
                                            } elseif ($status === 'terminiert') {
                                                $statusClass = 'warning';
                                            } elseif ($status === 'abgelehnt') {
                                                $statusClass = 'danger';
                                            }
                                        ?>
                                        <?php 
                                            // Konvertiere englischen Status zu deutschen Anzeigetexten
                                            $displayStatus = mapStatusToGerman($case['status']);
                                        ?>
                                        <span class="badge badge-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($displayStatus); ?></span>
                                    </td>
                                    <td>
                                        <a href="case_view.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-info">
                                            <span data-feather="eye"></span> Ansehen
                                        </a>
                                        <?php if (currentUserCan('cases', 'edit')): ?>
                                        <a href="case_edit.php?id=<?php echo $case['id']; ?>" class="btn btn-sm btn-primary">
                                            <span data-feather="edit"></span> Bearbeiten
                                        </a>
                                        <?php endif; ?>
                                        <?php if (currentUserCan('cases', 'delete')): ?>
                                        <form method="post" action="cases.php" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-delete">
                                                <span data-feather="trash-2"></span> Löschen
                                            <10button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">Keine Fälle gefunden.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
}
// Ende der renderCaseTable Funktion
?>
    </div>

<!-- Add Case Modal -->
<div class="modal fade" id="addCaseModal" tabindex="-1" aria-labelledby="addCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCaseModalLabel">Neue Akte anlegen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="cases.php" class="needs-validation" novalidate>
                <div class="modal-body">
                    <!-- Hidden Aktentyp - wird per JavaScript gesetzt -->
                    <input type="hidden" name="case_type" id="caseTypeInput" value="Straf">
                    
                    <hr>
                    
                    <div class="form-group">
                        <label for="custom_id">Aktenzeichen (optional)</label>
                        <input type="text" class="form-control" id="custom_id" name="custom_id" placeholder="z.B. A-2026-0001 oder aus Ermittlungsakte">
                        <small class="form-text text-muted">
                            Geben Sie das Aktenzeichen aus der Ermittlungsakte ein oder lassen Sie das Feld leer für automatische Generierung.
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="defendant" id="defendantLabel">Angeklagter *</label>
                        <input list="defendant_list" class="form-control" id="defendant" name="defendant" required placeholder="Name eingeben oder auswählen">
                        <datalist id="defendant_list">
                            <?php foreach ($defendants as $defendant): ?>
                                <option value="<?php echo htmlspecialchars($defendant['name']); ?>"><?php echo htmlspecialchars($defendant['tg_number'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </datalist>
                        <small class="form-text text-muted" id="defendantHelp">Vorhandene Angeklagte können gewählt oder neue eingetragen werden.</small>
                        <div class="invalid-feedback" id="defendantError">Bitte wählen oder erfassen Sie einen Angeklagten.</div>
                    </div>
                    <div class="form-group">
                        <label for="defendant_tg" id="tgLabel">TG-Nummer des Angeklagten</label>
                        <input type="text" class="form-control" id="defendant_tg" name="defendant_tg" placeholder="z.B. TG-1234">
                    </div>
                    <div class="form-group">
                        <label for="charge" id="chargeLabel">Anklage *</label>
                        <input type="text" class="form-control" id="charge" name="charge" required>
                        <div class="invalid-feedback" id="chargeError">Bitte geben Sie die Anklage ein.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="incident_date">Vorfallsdatum *</label>
                                <input type="date" class="form-control" id="incident_date" name="incident_date" required>
                                <div class="invalid-feedback">Bitte geben Sie das Vorfallsdatum ein.</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="limitation_id">Verjährungsfrist</label>
                                <select class="form-control" id="limitation_id" name="limitation_id">
                                    <option value="">Manuell/keine Vorlage</option>
                                    <?php foreach ($limitations as $lim): ?>
                                        <option value="<?php echo htmlspecialchars($lim['id']); ?>"><?php echo htmlspecialchars($lim['label'] . ' (' . $lim['days'] . ' Tage)'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Wird genutzt, um Verjährungsdatum aus dem Vorfallsdatum zu berechnen.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="expiration_date">Verjährungsdatum</label>
                                <input type="date" class="form-control" id="expiration_date" name="expiration_date">
                                <small class="form-text text-muted">Automatisch basierend auf ausgewählter Frist, kann überschrieben werden.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="bail_amount">Kaution</label>
                                <input type="text" class="form-control" id="bail_amount" name="bail_amount">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="district">Bezirk</label>
                                <select class="form-control" id="district" name="district">
                                    <option value="Ost">Ost</option>
                                    <option value="West">West</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="prosecutor">Staatsanwalt</label>
                                <select class="form-control" id="prosecutor" name="prosecutor">
                                    <option value="">Staatsanwalt auswählen</option>
                                    <?php foreach ($prosecutors as $prosecutor): ?>
                                        <option value="<?php echo htmlspecialchars($prosecutor['username']); ?>">
                                            <?php echo htmlspecialchars($prosecutor['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="judge">Richter</label>
                                <select class="form-control" id="judge" name="judge">
                                    <option value="">Richter auswählen</option>
                                    <?php foreach ($judges as $judge): ?>
                                        <option value="<?php echo htmlspecialchars($judge['username']); ?>">
                                            <?php echo htmlspecialchars($judge['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <?php
                            // Status-Optionen aus standardisierten Werten
                            $statusOptions = [
                                'open' => 'Offen',
                                'in_progress' => 'In Bearbeitung',
                                'pending' => 'Klageschrift eingereicht',
                                'accepted' => 'Klage angenommen',
                                'scheduled' => 'Terminiert',
                                'completed' => 'Abgeschlossen',
                                'rejected' => 'Abgelehnt',
                                'dismissed' => 'Eingestellt',
                                'appealed' => 'Berufung eingelegt',
                                'revision_requested' => 'Revision beantragt',
                                'revision_in_progress' => 'Revision in Bearbeitung',
                                'revision_completed' => 'Revision abgeschlossen',
                                'revision_rejected' => 'Revision abgelehnt',
                                'revision_verdict' => 'Revisionsurteil'
                            ];
                            
                            foreach ($statusOptions as $value => $label) {
                                echo '<option value="' . $value . '">' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Fall speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Feather Icons initialisieren
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Modal-Typ setzen basierend auf Button
    $('#addCaseModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const caseType = button.data('case-type');
        
        if (caseType) {
            $('#caseTypeInput').val(caseType);
            
            // Modal-Titel und Labels anpassen
            if (caseType === 'Straf') {
                // Header
                $('#addCaseModalLabel').html('<span data-feather="alert-triangle"></span> Neue Strafsache anlegen');
                $(this).find('.modal-header').removeClass('bg-primary').addClass('bg-danger').css('color', 'white');
                
                // Labels für Strafsache
                $('#defendantLabel').text('Angeklagter *');
                $('#defendantHelp').text('Vorhandene Angeklagte können gewählt oder neue eingetragen werden.');
                $('#defendantError').text('Bitte wählen oder erfassen Sie einen Angeklagten.');
                $('#tgLabel').text('TG-Nummer des Angeklagten');
                $('#chargeLabel').text('Anklage *');
                $('#chargeError').text('Bitte geben Sie die Anklage ein.');
            } else {
                // Header
                $('#addCaseModalLabel').html('<span data-feather="briefcase"></span> Neue Zivilsache anlegen');
                $(this).find('.modal-header').removeClass('bg-danger').addClass('bg-primary').css('color', 'white');
                
                // Labels für Zivilsache
                $('#defendantLabel').text('Partei *');
                $('#defendantHelp').text('Vorhandene Parteien können gewählt oder neue eingetragen werden.');
                $('#defendantError').text('Bitte wählen oder erfassen Sie eine Partei.');
                $('#tgLabel').text('TG-Nummer der Partei');
                $('#chargeLabel').text('Streitgegenstand *');
                $('#chargeError').text('Bitte geben Sie den Streitgegenstand ein.');
            }
            
            // Icons neu rendern
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        }
    });
    
    const defendantsData = <?php echo json_encode($defendants, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    const normalize = (val) => (val || '').trim().toLowerCase();

    const findByName = (name) => {
        const needle = normalize(name);
        return defendantsData.find(d => normalize(d.name) === needle);
    };

    const wireAutoFill = (nameSelector, tgSelector) => {
        const nameInput = document.querySelector(nameSelector);
        const tgInput = document.querySelector(tgSelector);
        if (!nameInput || !tgInput) return;

        const fill = () => {
            const match = findByName(nameInput.value);
            if (match && match.tg_number) {
                tgInput.value = match.tg_number;
            }
        };

        nameInput.addEventListener('change', fill);
        nameInput.addEventListener('blur', fill);
        nameInput.addEventListener('input', () => {
            if (!nameInput.value.trim()) {
                tgInput.value = '';
            }
        });
    };

    wireAutoFill('#defendant', '#defendant_tg');
});
</script>
