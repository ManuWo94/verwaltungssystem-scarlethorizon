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

// Enforce view permission for vacation
checkPermissionOrDie('vacation', 'view');

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Handle vacation request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'request') {
            $vacationData = [
                'id' => generateUniqueId(),
                'user_id' => $user_id,
                'user_name' => $username,
                'start_date' => sanitize($_POST['start_date'] ?? ''),
                'end_date' => sanitize($_POST['end_date'] ?? ''),
                'reason' => sanitize($_POST['reason'] ?? ''),
                'status' => 'pending',
                'date_requested' => date('Y-m-d H:i:s')
            ];
            
            // Validate required fields
            if (empty($vacationData['start_date']) || empty($vacationData['end_date'])) {
                $error = 'Bitte geben Sie sowohl Start- als auch Enddatum an.';
            } else {
                // Check if start date is before end date
                $startDate = strtotime($vacationData['start_date']);
                $endDate = strtotime($vacationData['end_date']);
                
                if ($startDate > $endDate) {
                    $error = 'Das Enddatum muss nach dem Startdatum liegen.';
                } else {
                    // Check for overlapping vacation requests
                    $vacations = loadJsonData('vacation.json');
                    $hasOverlap = false;
                    
                    foreach ($vacations as $vacation) {
                        if ($vacation['user_id'] === $user_id && $vacation['status'] !== 'rejected') {
                            $existingStart = strtotime($vacation['start_date']);
                            $existingEnd = strtotime($vacation['end_date']);
                            
                            // Check for overlap
                            if (($startDate <= $existingEnd) && ($endDate >= $existingStart)) {
                                $hasOverlap = true;
                                break;
                            }
                        }
                    }
                    
                    if ($hasOverlap) {
                        $error = 'Diese Anfrage überschneidet sich mit einem bestehenden Urlaubsantrag.';
                    } else {
                        if (insertRecord('vacation.json', $vacationData)) {
                            $message = 'Urlaubsantrag wurde erfolgreich eingereicht.';
                        } else {
                            $error = 'Fehler beim Einreichen des Urlaubsantrags.';
                        }
                    }
                }
            }
        } elseif ($action === 'cancel' && isset($_POST['vacation_id'])) {
            $vacationId = $_POST['vacation_id'];
            $vacation = findById('vacation.json', $vacationId);
            
            if (!$vacation) {
                $error = 'Urlaubsantrag nicht gefunden.';
            } elseif ($vacation['user_id'] !== $user_id && !checkUserPermission($_SESSION['user_id'], 'vacation', 'edit') && !isAdmin($user_id) && !isLeadership($user_id)) {
                // Only owner, admins or leadership can cancel someone else's request
                $error = 'Sie haben keine Berechtigung, diesen Urlaubsantrag zu stornieren.';
            } else {
                if (deleteRecord('vacation.json', $vacationId)) {
                    $message = 'Urlaubsantrag wurde erfolgreich gelöscht.';
                    
                    // Bei erfolgreichem Löschen zur Übersichtsseite zurückkehren
                    header('Location: vacation.php?message=' . urlencode($message));
                    exit;
                } else {
                    $error = 'Fehler beim Löschen des Urlaubsantrags.';
                }
            }
        } elseif (($action === 'approve' || $action === 'reject') && isset($_POST['vacation_id']) && (isAdmin($user_id) || isLeadership($user_id))) {
            $vacationId = $_POST['vacation_id'];
            $vacation = findById('vacation.json', $vacationId);
            
            if (!$vacation) {
                $error = 'Urlaubsantrag nicht gefunden.';
            } else {
                $vacation['status'] = ($action === 'approve') ? 'approved' : 'rejected';
                $vacation['reviewed_by'] = $user_id;
                $vacation['reviewed_by_name'] = $username;
                $vacation['review_date'] = date('Y-m-d H:i:s');
                $vacation['review_notes'] = sanitize($_POST['review_notes'] ?? '');
                
                if (updateRecord('vacation.json', $vacationId, $vacation)) {
                    $message = 'Urlaubsantrag erfolgreich ' . ($action === 'approve' ? 'genehmigt' : 'abgelehnt') . '.';
                } else {
                    $error = 'Fehler beim Aktualisieren des Urlaubsantrags.';
                }
            }
        }
    }
}

// Load vacations
$vacations = loadJsonData('vacation.json');

// For regular users, filter to show only their own requests
$userVacations = [];
$pendingVacations = [];
$upcomingVacations = [];

foreach ($vacations as $vacation) {
    if (isAdmin($user_id) || isLeadership($user_id)) {
        // For admins and leadership, add all vacations to userVacations for display
        $userVacations[] = $vacation;
        
        // Add pending vacations to pendingVacations
        if ($vacation['status'] === 'pending') {
            $pendingVacations[] = $vacation;
        }
    } else if ($vacation['user_id'] === $user_id) {
        // For regular users, add only their own vacations
        $userVacations[] = $vacation;
    }
    
    // Add approved and upcoming vacations to upcomingVacations
    if ($vacation['status'] === 'approved' && strtotime($vacation['end_date']) >= time()) {
        $upcomingVacations[] = $vacation;
    }
}

// Sort vacations by start date (most recent first)
usort($userVacations, function($a, $b) {
    return strtotime($b['start_date']) - strtotime($a['start_date']);
});

// Sort pending vacations by request date (oldest first)
usort($pendingVacations, function($a, $b) {
    return strtotime($a['date_requested']) - strtotime($b['date_requested']);
});

// Sort upcoming vacations by start date (soonest first)
usort($upcomingVacations, function($a, $b) {
    return strtotime($a['start_date']) - strtotime($b['start_date']);
});

// Get vacation details if ID is provided
$selectedVacation = null;
if (isset($_GET['id'])) {
    $vacationId = $_GET['id'];
    $vacation = findById('vacation.json', $vacationId);
    
    // Make sure the user can see this vacation request (their own, admin, or leadership)
    if ($vacation && ($vacation['user_id'] === $user_id || isAdmin($user_id) || isLeadership($user_id))) {
        $selectedVacation = $vacation;
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Urlaubsverwaltung</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#requestVacationModal">
                        <span data-feather="calendar"></span> Urlaub beantragen
                    </button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Left Column: Vacation Requests List -->
                <div class="col-md-5">
                    <?php if ((isAdmin($user_id) || isLeadership($user_id)) && !empty($pendingVacations)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-white">
                            <h4>Genehmigung ausstehend (<?php echo count($pendingVacations); ?>)</h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($pendingVacations as $vacation): ?>
                                    <a href="vacation.php?id=<?php echo $vacation['id']; ?>" class="list-group-item list-group-item-action <?php echo (isset($_GET['id']) && $_GET['id'] === $vacation['id']) ? 'active' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($vacation['user_name']); ?></h5>
                                            <small class="text-warning">Ausstehend</small>
                                        </div>
                                        <p class="mb-1">
                                            <?php echo date('d.m.Y', strtotime($vacation['start_date'])); ?> bis 
                                            <?php echo date('d.m.Y', strtotime($vacation['end_date'])); ?>
                                            (<?php echo round((strtotime($vacation['end_date']) - strtotime($vacation['start_date'])) / (60 * 60 * 24)) + 1; ?> Tage)
                                        </p>
                                        <small>Beantragt am: <?php echo date('d.m.Y', strtotime($vacation['date_requested'])); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4><?php echo isAdmin($user_id) ? 'Alle Urlaubsanträge' : 'Ihre Urlaubsanträge'; ?></h4>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (count($userVacations) > 0): ?>
                                    <?php foreach ($userVacations as $vacation): ?>
                                        <a href="vacation.php?id=<?php echo $vacation['id']; ?>" class="list-group-item list-group-item-action <?php echo (isset($_GET['id']) && $_GET['id'] === $vacation['id']) ? 'active' : ''; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <?php if (isAdmin($user_id) && $vacation['user_id'] !== $user_id): ?>
                                                    <h5 class="mb-1"><?php echo htmlspecialchars($vacation['user_name']); ?></h5>
                                                <?php else: ?>
                                                    <h5 class="mb-1"><?php echo date('m/d/Y', strtotime($vacation['start_date'])); ?> to <?php echo date('m/d/Y', strtotime($vacation['end_date'])); ?></h5>
                                                <?php endif; ?>
                                                
                                                <?php
                                                    $statusClass = 'secondary';
                                                    $statusText = 'Unbekannt';
                                                    
                                                    if ($vacation['status'] === 'pending') {
                                                        $statusClass = 'warning';
                                                        $statusText = 'Ausstehend';
                                                    } elseif ($vacation['status'] === 'approved') {
                                                        $statusClass = 'success';
                                                        $statusText = 'Genehmigt';
                                                    } elseif ($vacation['status'] === 'rejected') {
                                                        $statusClass = 'danger';
                                                        $statusText = 'Abgelehnt';
                                                    }
                                                ?>
                                                <small class="text-<?php echo $statusClass; ?>"><?php echo $statusText; ?></small>
                                            </div>
                                            
                                            <?php if (isAdmin($user_id) && $vacation['user_id'] !== $user_id): ?>
                                                <p class="mb-1">
                                                    <?php echo date('m/d/Y', strtotime($vacation['start_date'])); ?> to 
                                                    <?php echo date('m/d/Y', strtotime($vacation['end_date'])); ?>
                                                    (<?php echo round((strtotime($vacation['end_date']) - strtotime($vacation['start_date'])) / (60 * 60 * 24)) + 1; ?> days)
                                                </p>
                                            <?php else: ?>
                                                <p class="mb-1"><?php echo htmlspecialchars($vacation['reason']); ?></p>
                                            <?php endif; ?>
                                            
                                            <small>Requested: <?php echo date('m/d/Y', strtotime($vacation['date_requested'])); ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item">
                                        <p class="text-muted">Keine Urlaubsanträge gefunden.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Vacation Details or Calendar -->
                <div class="col-md-7">
                    <?php if ($selectedVacation): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>Urlaubsantrag-Details</h4>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">
                                    Urlaubsantrag von <?php echo htmlspecialchars($selectedVacation['user_name']); ?>
                                    <?php
                                        $statusClass = 'secondary';
                                        $statusText = 'Unbekannt';
                                        
                                        if ($selectedVacation['status'] === 'pending') {
                                            $statusClass = 'warning';
                                            $statusText = 'Genehmigung ausstehend';
                                        } elseif ($selectedVacation['status'] === 'approved') {
                                            $statusClass = 'success';
                                            $statusText = 'Genehmigt';
                                        } elseif ($selectedVacation['status'] === 'rejected') {
                                            $statusClass = 'danger';
                                            $statusText = 'Abgelehnt';
                                        }
                                    ?>
                                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p><strong>Startdatum:</strong> <?php echo date('l, F d, Y', strtotime($selectedVacation['start_date'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>End Date:</strong> <?php echo date('l, F d, Y', strtotime($selectedVacation['end_date'])); ?></p>
                                    </div>
                                </div>
                                
                                <p><strong>Duration:</strong> <?php echo round((strtotime($selectedVacation['end_date']) - strtotime($selectedVacation['start_date'])) / (60 * 60 * 24)) + 1; ?> days</p>
                                
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Reason for Vacation</h6>
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($selectedVacation['reason'])); ?></p>
                                    </div>
                                </div>
                                
                                <p><strong>Requested on:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($selectedVacation['date_requested'])); ?></p>
                                
                                <?php if ($selectedVacation['status'] !== 'pending'): ?>
                                    <div class="card <?php echo $selectedVacation['status'] === 'approved' ? 'border-success' : 'border-danger'; ?> mb-3">
                                        <div class="card-header <?php echo $selectedVacation['status'] === 'approved' ? 'bg-success' : 'bg-danger'; ?> text-white">
                                            <?php echo $selectedVacation['status'] === 'approved' ? 'Approval' : 'Rejection'; ?> Information
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Decision by:</strong> <?php echo htmlspecialchars($selectedVacation['reviewed_by_name']); ?></p>
                                            <p><strong>Date:</strong> <?php echo date('F d, Y \a\t h:i A', strtotime($selectedVacation['review_date'])); ?></p>
                                            
                                            <?php if (!empty($selectedVacation['review_notes'])): ?>
                                                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($selectedVacation['review_notes'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <!-- Löschformular für jeden Status, wenn der Benutzer der Eigentümer ist oder Admin ist -->
                                    <?php if ($selectedVacation['user_id'] === $user_id || isAdmin($user_id) || isLeadership($user_id)): ?>
                                        <form method="post" action="vacation.php" class="d-inline">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="vacation_id" value="<?php echo $selectedVacation['id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie diesen Urlaubsantrag löschen möchten?')">
                                                <span data-feather="trash-2"></span> Urlaubsantrag löschen
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($selectedVacation['status'] === 'pending'): ?>
                                        <!-- Genehmigungsbuttons nur für Admins/Leadership und nur bei ausstehenden Anträgen -->
                                        <?php if (isAdmin($user_id) || isLeadership($user_id)): ?>
                                            <button type="button" class="btn btn-success ml-2" data-toggle="modal" data-target="#approveVacationModal">
                                                <span data-feather="check"></span> Genehmigen
                                            </button>
                                            <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#rejectVacationModal">
                                                <span data-feather="x"></span> Ablehnen
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Zurück zur Liste Button -->
                                    <a href="vacation.php" class="btn btn-secondary ml-2">
                                        <span data-feather="arrow-left"></span> Zurück zur Liste
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h4>Bevorstehende Urlaubszeiten</h4>
                            </div>
                            <div class="card-body">
                                <?php if (count($upcomingVacations) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Mitarbeiter</th>
                                                    <th>Startdatum</th>
                                                    <th>Enddatum</th>
                                                    <th>Dauer</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($upcomingVacations as $vacation): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($vacation['user_name']); ?></td>
                                                        <td><?php echo date('m/d/Y', strtotime($vacation['start_date'])); ?></td>
                                                        <td><?php echo date('m/d/Y', strtotime($vacation['end_date'])); ?></td>
                                                        <td><?php echo round((strtotime($vacation['end_date']) - strtotime($vacation['start_date'])) / (60 * 60 * 24)) + 1; ?> Tage</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">Keine bevorstehenden Urlaubszeiten geplant.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h4>Urlaubsrichtlinien</h4>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h5 class="alert-heading">Urlaubsrichtlinien des Justizministeriums</h5>
                                    <p>Hier können Urlaubstage bzw. Tage von Abwesenheit eingetragen werden, spätestens bei maximal 5 Tagen Abwesenheit ist Urlaub einzutragen.</p>
                                    <p>Dieser wird zwar als Antrag gestellt, dient jedoch nur zur Übersicht von Abwesenheiten.</p>
                                    <p>Urlaubstage werden im Kalender übertragen für jeden sichtbar, zur besseren Planung. Jede Überschneidung mit geplanten Gerichtsterminen muss vor Urlaubsbeantragung mit der Leitungsebene abgeklärt werden.</p>
                                </div>
                                
                                <p>Um Urlaub zu beantragen, klicken Sie auf die Schaltfläche "Urlaub beantragen" oben auf der Seite.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Request Vacation Modal -->
<div class="modal fade" id="requestVacationModal" tabindex="-1" aria-labelledby="requestVacationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestVacationModalLabel">Urlaub beantragen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="vacation.php">
                <input type="hidden" name="action" value="request">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="start_date">Startdatum *</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="end_date">Enddatum *</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label for="reason">Grund für den Urlaub *</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Antrag einreichen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ((isAdmin($user_id) || isLeadership($user_id)) && $selectedVacation && $selectedVacation['status'] === 'pending'): ?>
<!-- Approve Vacation Modal -->
<div class="modal fade" id="approveVacationModal" tabindex="-1" aria-labelledby="approveVacationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveVacationModalLabel">Urlaubsantrag genehmigen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="vacation.php">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="vacation_id" value="<?php echo $selectedVacation['id']; ?>">
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie den Urlaubsantrag von <?php echo htmlspecialchars($selectedVacation['user_name']); ?> vom <?php echo date('d.m.Y', strtotime($selectedVacation['start_date'])); ?> bis zum <?php echo date('d.m.Y', strtotime($selectedVacation['end_date'])); ?> genehmigen möchten?</p>
                    <div class="form-group">
                        <label for="approve_notes">Anmerkungen (Optional)</label>
                        <textarea class="form-control" id="approve_notes" name="review_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Antrag genehmigen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Vacation Modal -->
<div class="modal fade" id="rejectVacationModal" tabindex="-1" aria-labelledby="rejectVacationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectVacationModalLabel">Urlaubsantrag ablehnen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="vacation.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="vacation_id" value="<?php echo $selectedVacation['id']; ?>">
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie den Urlaubsantrag von <?php echo htmlspecialchars($selectedVacation['user_name']); ?> vom <?php echo date('d.m.Y', strtotime($selectedVacation['start_date'])); ?> bis zum <?php echo date('d.m.Y', strtotime($selectedVacation['end_date'])); ?> ablehnen möchten?</p>
                    <div class="form-group">
                        <label for="reject_notes">Grund für die Ablehnung *</label>
                        <textarea class="form-control" id="reject_notes" name="review_notes" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Antrag ablehnen</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
