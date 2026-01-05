<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Enforce view permission for revisions
checkPermissionOrDie('revisions', 'view');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Status to filter
$statusFilter = $_GET['status'] ?? '';

// Get all cases
$allCases = loadJsonData('cases.json');

// Filter cases with revision status
$revisionCases = array_filter($allCases, function($case) {
    $status = strtolower($case['status'] ?? '');
    return strpos($status, 'revision') !== false;
});

// Apply specific status filter if set
if (!empty($statusFilter)) {
    $revisionCases = array_filter($revisionCases, function($case) use ($statusFilter) {
        return strtolower($case['status']) === strtolower($statusFilter);
    });
}

// Die mapStatusToGerman-Funktion ist bereits in includes/functions.php definiert

// Handle review action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_revision') {
        // Require edit permission on cases to process revisions
        checkPermissionOrDie('cases', 'edit');

        $caseId = $_POST['case_id'] ?? '';
        $newStatus = $_POST['revision_status'] ?? '';
        $notes = sanitize($_POST['notes'] ?? '');
        
        // Debug-Ausgabe, um zu sehen, welche Daten gesendet werden
        error_log("Revision update POST data: case_id=$caseId, status=$newStatus");
        
        if (empty($caseId) || empty($newStatus)) {
            $error = 'Alle erforderlichen Felder müssen ausgefüllt werden.';
        } else {
            $case = findById('cases.json', $caseId);
            
            if (!$case) {
                $error = "Fall mit ID '$caseId' konnte nicht gefunden werden.";
                error_log("Fehler: Fall mit ID '$caseId' konnte nicht gefunden werden.");
            } else {
                // Prüfen ob der Benutzer berechtigt ist (Richter oder Leadership)
                $isJudge = hasPermission($user_id, 'cases.preside_trials');
                $isLeadership = isLeadership($user_id);
                
                if (!$isJudge && !$isLeadership) {
                    $error = 'Sie haben keine Berechtigung, Revisionen zu verarbeiten.';
                } else {
                    // Update case status
                    $case['status'] = $newStatus;
                    error_log("Revisionsstatus wird aktualisiert: " . $case['id'] . " => " . $newStatus);
                    
                    // Aktualisiere den Prozessor
                    $case['revision_processor_id'] = $user_id;
                    $case['revision_processor_name'] = $username;
                    $case['revision_process_date'] = date('Y-m-d H:i:s');
                    
                    // Entscheide basierend auf dem Status, welche zusätzlichen Felder zu setzen sind
                    switch ($newStatus) {
                        case 'revision_in_progress':
                            // Revision angenommen - wird bearbeitet
                            $case['revision_accepted_date'] = date('Y-m-d H:i:s');
                            $case['revision_accepted_by'] = $username;
                            $case['revision_accepted_by_id'] = $user_id;
                            
                            // Richter setzen
                            $case['revision_judge_id'] = $user_id;
                            $case['revision_judge_name'] = $username;
                            
                            $updateNote = "Revision angenommen von $username am " . date('d.m.Y H:i:s');
                            $successMessage = 'Die Revision wurde angenommen und in Bearbeitung gesetzt.';
                            break;
                            
                        case 'revision_rejected':
                            // Revision abgelehnt
                            $case['revision_rejected_date'] = date('Y-m-d H:i:s');
                            $case['revision_rejected_by'] = $username;
                            $case['revision_rejected_by_id'] = $user_id;
                            $case['revision_rejection_reason'] = $notes;
                            
                            $updateNote = "Revision abgelehnt von $username am " . date('d.m.Y H:i:s');
                            if (!empty($notes)) {
                                $updateNote .= "\nBegründung: $notes";
                            }
                            $successMessage = 'Die Revision wurde abgelehnt.';
                            break;
                            
                        case 'revision_completed':
                            // Revision abgeschlossen mit Urteil
                            $verdictText = sanitize($_POST['verdict_text'] ?? '');
                            $verdictDate = sanitize($_POST['verdict_date'] ?? date('Y-m-d'));
                            
                            if (empty($verdictText)) {
                                $error = 'Bei Abschluss einer Revision muss ein Urteilstext eingegeben werden.';
                                goto process_end;
                            }
                            
                            // Urteilsdaten speichern
                            $case['revision_verdict'] = $verdictText;
                            $case['revision_verdict_date'] = $verdictDate;
                            $case['revision_verdict_by'] = $username;
                            $case['revision_verdict_by_id'] = $user_id;
                            
                            // Urteil zur Notiz hinzufügen
                            $updateNote = "Revisionsurteil von $username am " . date('d.m.Y H:i:s') . ":\n$verdictText";
                            $successMessage = 'Revisionsurteil wurde erfolgreich eingetragen.';
                            break;
                            
                        default:
                            // Standard-Update-Notiz
                            $updateNote = "Revisionsstatus auf '" . mapStatusToGerman($newStatus) . "' aktualisiert von $username am " . date('d.m.Y H:i:s');
                            $successMessage = 'Revisionsstatus wurde erfolgreich aktualisiert.';
                    }
                    
                    // Notizen hinzufügen
                    if (!empty($notes) && $newStatus !== 'revision_rejected') {
                        $updateNote .= "\n\nHinweis: $notes";
                    }
                    
                    // Sicherstellen, dass notes ein Array ist
                    if (!isset($case['notes']) || !is_array($case['notes'])) {
                        $case['notes'] = [];
                    }
                    
                    // Neue Notiz am Anfang des Arrays hinzufügen
                    array_unshift($case['notes'], [
                        'date' => date('Y-m-d H:i:s'),
                        'user' => $username,
                        'note' => $updateNote
                    ]);
                    
                    // Revisionshistorie aktualisieren
                    if (!isset($case['revision_history']) || !is_array($case['revision_history'])) {
                        $case['revision_history'] = [];
                    }
                    
                    // Neue Statusaktualisierung zur Historie hinzufügen
                    array_unshift($case['revision_history'], [
                        'status' => $newStatus,
                        'date' => date('Y-m-d H:i:s'),
                        'user_id' => $user_id,
                        'username' => $username,
                        'notes' => $notes,
                        'verdict' => $verdictText ?? null,
                        'verdict_date' => $verdictDate ?? null
                    ]);
                    
                    // Update revision fields
                    $case['revision_updated_by'] = $username;
                    $case['revision_updated_date'] = date('Y-m-d H:i:s');
                    
                    if (updateRecord('cases.json', $caseId, $case)) {
                        // Direkt die Erfolgs-Nachricht setzen ohne Weiterleitung
                        $message = $successMessage;
                    } else {
                        $error = 'Fehler beim Aktualisieren des Revisionsstatus.';
                    }
                }
            }
        }
    }
}

// Label für goto-Statement
process_end:

// Sort cases by revision requested date (newest first)
usort($revisionCases, function($a, $b) {
    $dateA = isset($a['revision_requested_date']) ? strtotime($a['revision_requested_date']) : 0;
    $dateB = isset($b['revision_requested_date']) ? strtotime($b['revision_requested_date']) : 0;
    
    return $dateB - $dateA;
});
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Revisionsverwaltung</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Filter bar -->
            <div class="mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5>Filter</h5>
                    </div>
                    <div class="card-body">
                        <form class="form-inline" method="get" action="revisions.php">
                            <div class="form-group mb-2 mr-3">
                                <label for="status" class="mr-2">Status:</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="" <?php echo empty($statusFilter) ? 'selected' : ''; ?>>Alle Revisionen</option>
                                    <option value="revision_requested" <?php echo $statusFilter === 'revision_requested' ? 'selected' : ''; ?>>Revision beantragt</option>
                                    <option value="revision_in_progress" <?php echo $statusFilter === 'revision_in_progress' ? 'selected' : ''; ?>>Revision in Bearbeitung</option>
                                    <option value="revision_completed" <?php echo $statusFilter === 'revision_completed' ? 'selected' : ''; ?>>Revision abgeschlossen</option>
                                    <option value="revision_rejected" <?php echo $statusFilter === 'revision_rejected' ? 'selected' : ''; ?>>Revision abgelehnt</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary mb-2">Filtern</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php
            // Filter und gruppiere die Fälle nach ihrem Status
            $requestedRevisions = array_filter($revisionCases, function($case) {
                $status = strtolower($case['status'] ?? '');
                return $status === 'revision_requested' || $status === 'revision beantragt';
            });
            
            $inProgressRevisions = array_filter($revisionCases, function($case) {
                $status = strtolower($case['status'] ?? '');
                return $status === 'revision_in_progress' || $status === 'revision in bearbeitung';
            });
            
            $completedRevisions = array_filter($revisionCases, function($case) {
                $status = strtolower($case['status'] ?? '');
                return $status === 'revision_completed' || $status === 'revision abgeschlossen';
            });
            
            $rejectedRevisions = array_filter($revisionCases, function($case) {
                $status = strtolower($case['status'] ?? '');
                return $status === 'revision_rejected' || $status === 'revision abgelehnt';
            });
            
            // Definiere eine Funktion zum Rendern der Tabelle basierend auf dem Status
            function renderRevisionTable($cases, $title, $statusType) {
                if (empty($cases) && !empty($_GET['status'])) {
                    return ''; // Keine Tabelle anzeigen, wenn gefiltert wird und keine Fälle vorhanden sind
                }
                
                $html = '<div class="card mb-4">
                    <div class="card-header bg-' . ($statusType === 'requested' ? 'info' : 
                                                ($statusType === 'progress' ? 'primary' : 
                                                ($statusType === 'completed' ? 'success' : 'danger'))) . ' text-white">
                        <h5>' . $title . ' (' . count($cases) . ')</h5>
                    </div>
                    <div class="card-body">';
                
                if (empty($cases)) {
                    $html .= '<p class="text-center text-muted">Keine Fälle gefunden.</p>';
                } else {
                    $html .= '<div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Angeklagter</th>
                                    <th>Anklage</th>
                                    <th>Status</th>
                                    <th>Revisionsgrund</th>
                                    <th>Revision beantragt von</th>
                                    <th>Datum der Beantragung</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>';
                    
                    foreach ($cases as $case) {
                        $status = strtolower($case['status']);
                        $statusClass = 'secondary';
                        
                        if ($status === 'revision_requested' || $status === 'revision beantragt') {
                            $statusClass = 'info';
                        } elseif ($status === 'revision_in_progress' || $status === 'revision in bearbeitung') {
                            $statusClass = 'primary';
                        } elseif ($status === 'revision_completed' || $status === 'revision abgeschlossen') {
                            $statusClass = 'success';
                        } elseif ($status === 'revision_rejected' || $status === 'revision abgelehnt') {
                            $statusClass = 'danger';
                        }
                        
                        $html .= '<tr>
                            <td>' . htmlspecialchars($case['defendant']) . '</td>
                            <td>' . htmlspecialchars($case['charge']) . '</td>
                            <td><span class="badge badge-' . $statusClass . '">' . htmlspecialchars($case['status']) . '</span></td>
                            <td>' . htmlspecialchars($case['revision_reason'] ?? 'Nicht angegeben') . '</td>
                            <td>' . htmlspecialchars($case['revision_requested_by'] ?? 'Unbekannt') . '</td>
                            <td>' . (isset($case['revision_requested_date']) ? formatDate($case['revision_requested_date'], true) : 'Unbekannt') . '</td>
                            <td>
                                <a href="case_edit.php?id=' . $case['id'] . '" class="btn btn-sm btn-secondary">
                                    <span data-feather="folder"></span> Fall anzeigen
                                </a>';
                        
                        if ($statusType === 'requested') {
                            // Debug-Ausgabe zu jedem Fall mit ID
                            error_log("Rendering button for requested revision: " . $case['id']);
                            
                            $html .= '
                                <!-- Direkte Links für die Revision -->
                                <div class="mt-2">
                                    <form method="post" action="./revisions.php" class="d-inline-block mr-2">
                                        <input type="hidden" name="action" value="update_revision">
                                        <input type="hidden" name="revision_status" value="revision_in_progress">
                                        <input type="hidden" name="case_id" value="' . $case['id'] . '">
                                        <input type="hidden" name="notes" value="Revision angenommen">
                                        <button type="submit" class="btn btn-sm btn-success">Revision annehmen</button>
                                    </form>
                                    
                                    <form method="post" action="./revisions.php" class="d-inline-block">
                                        <input type="hidden" name="action" value="update_revision">
                                        <input type="hidden" name="revision_status" value="revision_rejected">
                                        <input type="hidden" name="case_id" value="' . $case['id'] . '">
                                        <input type="hidden" name="notes" value="Revision abgelehnt">
                                        <button type="submit" class="btn btn-sm btn-danger">Revision ablehnen</button>
                                    </form>
                                </div>';
                        } elseif ($statusType === 'progress') {
                            // Debug-Ausgabe
                            error_log("Rendering complete button for in-progress revision: " . $case['id']);
                            
                            $html .= '
                                <a href="case_edit.php?id=' . $case['id'] . '" class="btn btn-sm btn-primary">
                                    <span data-feather="edit"></span> Revisionsurteil eintragen
                                </a>';
                        }
                        
                        $html .= '
                            </td>
                        </tr>';
                    }
                    
                    $html .= '</tbody>
                        </table>
                    </div>';
                }
                
                $html .= '</div></div>';
                
                return $html;
            }
            
            // Wenn ein spezifischer Filter gesetzt ist, nur die entsprechende Tabelle anzeigen
            if (!empty($statusFilter)) {
                if ($statusFilter === 'revision_requested') {
                    echo renderRevisionTable($requestedRevisions, 'Zur Prüfung beantragte Revisionen', 'requested');
                } elseif ($statusFilter === 'revision_in_progress') {
                    echo renderRevisionTable($inProgressRevisions, 'Fälle in Revision', 'progress');
                } elseif ($statusFilter === 'revision_completed') {
                    echo renderRevisionTable($completedRevisions, 'Abgeschlossene Revisionen', 'completed');
                } elseif ($statusFilter === 'revision_rejected') {
                    echo renderRevisionTable($rejectedRevisions, 'Abgelehnte Revisionen', 'rejected');
                }
            } else {
                // Keine Filterung - alle Tabellen anzeigen, gruppiert nach Status
                if (!empty($_GET['status']) && empty($revisionCases)):
            ?>
                <p class="text-center text-muted">Keine Fälle mit dem gewählten Revisionsstatus gefunden.</p>
            <?php
                else:
                    // Zeige erst die beantragten Revisionen
                    echo renderRevisionTable($requestedRevisions, 'Zur Prüfung beantragte Revisionen', 'requested');
                    
                    // Dann die in Bearbeitung befindlichen Revisionen
                    echo renderRevisionTable($inProgressRevisions, 'Fälle in Revision', 'progress');
                    
                    // Dann die abgeschlossenen Revisionen
                    echo renderRevisionTable($completedRevisions, 'Abgeschlossene Revisionen', 'completed');
                    
                    // Zuletzt die abgelehnten Revisionen
                    echo renderRevisionTable($rejectedRevisions, 'Abgelehnte Revisionen', 'rejected');
                endif;
            }
            ?>
        </main>
    </div>
</div>

<!-- Accept Revision Modal -->
<div class="modal fade" id="acceptRevisionModal" tabindex="-1" aria-labelledby="acceptRevisionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="acceptRevisionModalLabel">Revision annehmen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="./revisions.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>">
                <input type="hidden" name="action" value="update_revision">
                <input type="hidden" name="revision_status" value="revision_in_progress">
                <input type="hidden" name="case_id" id="accept_case_id">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        Wenn Sie diese Revision annehmen, wird sie in die Kategorie "Fälle in Revision" verschoben und kann dann weiter bearbeitet werden.
                    </div>
                    <div class="form-group">
                        <label for="accept_defendant">Angeklagter</label>
                        <input type="text" class="form-control" id="accept_defendant" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="accept_charge">Anklage</label>
                        <input type="text" class="form-control" id="accept_charge" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="accept_notes">Hinweise zur Annahme der Revision</label>
                        <textarea class="form-control" id="accept_notes" name="notes" rows="4" placeholder="Optionale Hinweise zur Annahme der Revision"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Revision annehmen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Revision Modal -->
<div class="modal fade" id="rejectRevisionModal" tabindex="-1" aria-labelledby="rejectRevisionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="rejectRevisionModalLabel">Revision ablehnen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="./revisions.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>">
                <input type="hidden" name="action" value="update_revision">
                <input type="hidden" name="revision_status" value="revision_rejected">
                <input type="hidden" name="case_id" id="reject_case_id">
                
                <div class="modal-body">
                    <div class="alert alert-warning">
                        Wenn Sie diese Revision ablehnen, wird sie in die Kategorie "Abgelehnte Revisionen" verschoben und kann nicht mehr bearbeitet werden.
                    </div>
                    <div class="form-group">
                        <label for="reject_defendant">Angeklagter</label>
                        <input type="text" class="form-control" id="reject_defendant" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="reject_charge">Anklage</label>
                        <input type="text" class="form-control" id="reject_charge" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="reject_notes">Begründung für die Ablehnung *</label>
                        <textarea class="form-control" id="reject_notes" name="notes" rows="4" placeholder="Bitte geben Sie eine Begründung für die Ablehnung der Revision an" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Revision ablehnen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Revision Modal -->
<div class="modal fade" id="completeRevisionModal" tabindex="-1" aria-labelledby="completeRevisionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="completeRevisionModalLabel">Revisionsurteil eintragen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="./revisions.php<?php echo !empty($statusFilter) ? '?status=' . urlencode($statusFilter) : ''; ?>">
                <input type="hidden" name="action" value="update_revision">
                <input type="hidden" name="revision_status" value="revision_completed">
                <input type="hidden" name="case_id" id="complete_case_id">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        Bitte geben Sie das Revisionsurteil ein, um die Revision abzuschließen.
                    </div>
                    <div class="form-group">
                        <label for="complete_defendant">Angeklagter</label>
                        <input type="text" class="form-control" id="complete_defendant" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="complete_charge">Anklage</label>
                        <input type="text" class="form-control" id="complete_charge" readonly disabled>
                    </div>
                    <div class="form-group">
                        <label for="verdict_text">Revisionsurteil *</label>
                        <textarea class="form-control" id="verdict_text" name="verdict_text" rows="4" placeholder="Revisionsurteil eingeben" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="verdict_date">Urteilsdatum *</label>
                        <input type="date" class="form-control" id="verdict_date" name="verdict_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="complete_notes">Zusätzliche Hinweise</label>
                        <textarea class="form-control" id="complete_notes" name="notes" rows="4" placeholder="Optionale zusätzliche Hinweise zum Revisionsurteil"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Revisionsurteil speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debug-Funktion
    function logModalData(modalName, button) {
        console.log(modalName + " Daten:", {
            caseId: button.data('caseid'),
            defendant: button.data('defendant'),
            charge: button.data('charge')
        });
    }
    
    // JQuery-kompatible Funktionen für alle Bootstrap-Modals
    
    // Event-Listener für das Accept-Revision-Modal
    var acceptModal = document.getElementById('acceptRevisionModal');
    if (acceptModal) {
        $(acceptModal).on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var caseId = button.data('caseid');
            var defendant = button.data('defendant');
            var charge = button.data('charge');
            
            // Debug-Ausgabe
            logModalData("Accept-Modal", button);
            
            // Überprüfung auf gültige ID
            if (!caseId || caseId === 'undefined') {
                console.error("Fehler: Keine gültige Fall-ID für das Accept-Modal gefunden!");
                alert("Fehler: Die Fall-ID konnte nicht ermittelt werden.");
                return false; // Modal nicht öffnen
            }
            
            document.getElementById('accept_case_id').value = caseId;
            document.getElementById('accept_defendant').value = defendant;
            document.getElementById('accept_charge').value = charge;
        });
    }
    
    // Event-Listener für das Reject-Revision-Modal
    var rejectModal = document.getElementById('rejectRevisionModal');
    if (rejectModal) {
        $(rejectModal).on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var caseId = button.data('caseid');
            var defendant = button.data('defendant');
            var charge = button.data('charge');
            
            // Debug-Ausgabe
            logModalData("Reject-Modal", button);
            
            // Überprüfung auf gültige ID
            if (!caseId || caseId === 'undefined') {
                console.error("Fehler: Keine gültige Fall-ID für das Reject-Modal gefunden!");
                alert("Fehler: Die Fall-ID konnte nicht ermittelt werden.");
                return false; // Modal nicht öffnen
            }
            
            document.getElementById('reject_case_id').value = caseId;
            document.getElementById('reject_defendant').value = defendant;
            document.getElementById('reject_charge').value = charge;
        });
    }
    
    // Event-Listener für das Complete-Revision-Modal
    var completeModal = document.getElementById('completeRevisionModal');
    if (completeModal) {
        $(completeModal).on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var caseId = button.data('caseid');
            var defendant = button.data('defendant');
            var charge = button.data('charge');
            
            // Debug-Ausgabe
            logModalData("Complete-Modal", button);
            
            // Überprüfung auf gültige ID
            if (!caseId || caseId === 'undefined') {
                console.error("Fehler: Keine gültige Fall-ID für das Complete-Modal gefunden!");
                alert("Fehler: Die Fall-ID konnte nicht ermittelt werden.");
                return false; // Modal nicht öffnen
            }
            
            document.getElementById('complete_case_id').value = caseId;
            document.getElementById('complete_defendant').value = defendant;
            document.getElementById('complete_charge').value = charge;
        });
    }
    
    // Status-Filter-Änderungsüberwachung mit nativer JS
    var statusFilter = document.getElementById('status');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    // Debugging: Zeige alle Revision-Buttons und deren Daten an
    console.log("Footer-Script geladen, Modals initialisiert");
    
    // Überprüfe alle Modal-Button-Daten
    $('button[data-target="#acceptRevisionModal"]').each(function(index) {
        console.log("Accept-Button #" + index + " data:", {
            caseId: $(this).data('caseid'),
            defendant: $(this).data('defendant'),
            charge: $(this).data('charge')
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>