<?php
/**
 * Department of Justice - Haftbefehle (Arrest Warrants) Module
 * 
 * This module allows judicial officials to create, manage and execute arrest warrants
 * as well as track their status. The module is restricted from students and trainees.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// Prüfe, ob der Benutzer die Berechtigung hat, Haftbefehle anzuzeigen
// Benutze checkPermissionOrDie statt checkPermissionAndRedirect für Konsistenz mit anderen Modulen
checkPermissionOrDie('warrants', 'view');

// Nach der Authentifizierung kann der Header eingebunden werden
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Process form submission if any
$message = '';
$messageType = '';
$formData = [];

// Default status filter (show all)
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Process warrant create/edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Create new warrant
    if ($action === 'create' && currentUserCan('warrants', 'create')) {
        $suspect = isset($_POST['suspect']) ? sanitize($_POST['suspect']) : '';
        $caseNumber = isset($_POST['case_number']) ? sanitize($_POST['case_number']) : '';
        $issueDate = isset($_POST['issue_date']) ? sanitize($_POST['issue_date']) : date('Y-m-d');
        
        // Validate input
        if (empty($suspect)) {
            $message = 'Bitte füllen Sie alle Pflichtfelder aus.';
            $messageType = 'danger';
            $formData = $_POST;
        } else {
            // Handle file upload
            $warrantFile = '';
            if (isset($_FILES['warrant_file']) && $_FILES['warrant_file']['error'] === UPLOAD_ERR_OK) {
                $fileName = $_FILES['warrant_file']['name'];
                $fileSize = $_FILES['warrant_file']['size'];
                $fileTmpName = $_FILES['warrant_file']['tmp_name'];
                $fileType = $_FILES['warrant_file']['type'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                // Check if file is an image
                $allowedExtensions = ['jpg', 'jpeg', 'png'];
                if (in_array($fileExtension, $allowedExtensions)) {
                    // Generate unique file name
                    $newFileName = uniqid('warrant_', true) . '.' . $fileExtension;
                    $uploadDir = '../uploads/warrants/';
                    $uploadPath = $uploadDir . $newFileName;
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    // Move file to upload directory
                    if (move_uploaded_file($fileTmpName, $uploadPath)) {
                        $warrantFile = $newFileName;
                    } else {
                        $message = 'Fehler beim Hochladen der Datei.';
                        $messageType = 'danger';
                        $formData = $_POST;
                    }
                } else {
                    $message = 'Nur JPG, JPEG und PNG-Dateien sind erlaubt.';
                    $messageType = 'danger';
                    $formData = $_POST;
                }
            }
            
            // If validation and file upload are successful, create new warrant
            if (empty($message)) {
                $warrant = [
                    'id' => generateUUID(),
                    'suspect' => $suspect,
                    'case_number' => $caseNumber,
                    'issue_date' => $issueDate,
                    'status' => 'open',
                    'creator_id' => $_SESSION['user_id'],
                    'creator_name' => getUserFullName($_SESSION['user_id']),
                    'executed_by' => null,
                    'executed_date' => null,
                    'execution_notes' => null,
                    'file' => $warrantFile
                ];
                
                // Save warrant to JSON file
                $warrants = getJsonData('warrants.json');
                $warrants[] = $warrant;
                if (saveJsonData('warrants.json', $warrants)) {
                    $message = 'Haftbefehl wurde erfolgreich erstellt.';
                    $messageType = 'success';
                } else {
                    $message = 'Fehler beim Speichern des Haftbefehls.';
                    $messageType = 'danger';
                    $formData = $_POST;
                }
            }
        }
    }
    
    // Edit existing warrant
    elseif ($action === 'edit' && currentUserCan('warrants', 'edit')) {
        $warrantId = isset($_POST['warrant_id']) ? sanitize($_POST['warrant_id']) : '';
        $status = isset($_POST['status']) ? sanitize($_POST['status']) : '';
        $notes = isset($_POST['execution_notes']) ? sanitize($_POST['execution_notes']) : '';
        
        // Check if we have a valid warrant ID
        if (empty($warrantId)) {
            $message = 'Ungültiger Haftbefehl.';
            $messageType = 'danger';
        } else {
            // Load warrants from JSON file
            $warrants = getJsonData('warrants.json');
            $warrantIndex = -1;
            
            // Find warrant by ID
            foreach ($warrants as $index => $warrant) {
                if ($warrant['id'] === $warrantId) {
                    $warrantIndex = $index;
                    break;
                }
            }
            
            // Update warrant status if found
            if ($warrantIndex >= 0) {
                // Prevent U.S. President and Secretary from executing warrants
                $isPresident = hasUserRole('President');
                $isSecretary = hasUserRole('Secretary');
                
                if ($status === 'executed' && ($isPresident || $isSecretary)) {
                    $message = 'U.S. President und Secretary können keine Haftbefehle vollstrecken.';
                    $messageType = 'danger';
                } else {
                    $warrants[$warrantIndex]['status'] = $status;
                    
                    // If marking as executed, add execution details
                    if ($status === 'executed') {
                        $warrants[$warrantIndex]['executed_by'] = $_SESSION['user_id'];
                        $warrants[$warrantIndex]['executed_date'] = date('Y-m-d H:i:s');
                        $warrants[$warrantIndex]['execution_notes'] = $notes;
                    }
                    
                    // Save changes
                    if (saveJsonData('warrants.json', $warrants)) {
                        $message = 'Haftbefehl wurde erfolgreich aktualisiert.';
                        $messageType = 'success';
                    } else {
                        $message = 'Fehler beim Speichern der Änderungen.';
                        $messageType = 'danger';
                    }
                }
            } else {
                $message = 'Haftbefehl nicht gefunden.';
                $messageType = 'danger';
            }
        }
    }
    
    // Delete warrant
    elseif ($action === 'delete' && currentUserCan('warrants', 'delete')) {
        $warrantId = isset($_POST['warrant_id']) ? sanitize($_POST['warrant_id']) : '';
        
        // Check if we have a valid warrant ID
        if (empty($warrantId)) {
            $message = 'Ungültiger Haftbefehl.';
            $messageType = 'danger';
        } else {
            // Load warrants from JSON file
            $warrants = getJsonData('warrants.json');
            $warrantIndex = -1;
            $warrantFile = '';
            
            // Find warrant by ID
            foreach ($warrants as $index => $warrant) {
                if ($warrant['id'] === $warrantId) {
                    $warrantIndex = $index;
                    $warrantFile = $warrant['file'];
                    break;
                }
            }
            
            // Delete warrant if found
            if ($warrantIndex >= 0) {
                // Delete associated file if exists
                if (!empty($warrantFile)) {
                    $filePath = '../uploads/warrants/' . $warrantFile;
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                
                // Remove warrant from array
                array_splice($warrants, $warrantIndex, 1);
                
                // Save changes
                if (saveJsonData('warrants.json', $warrants)) {
                    $message = 'Haftbefehl wurde erfolgreich gelöscht.';
                    $messageType = 'success';
                } else {
                    $message = 'Fehler beim Löschen des Haftbefehls.';
                    $messageType = 'danger';
                }
            } else {
                $message = 'Haftbefehl nicht gefunden.';
                $messageType = 'danger';
            }
        }
    }
}

// Load warrants from JSON file
$warrants = getJsonData('warrants.json');

// Filter warrants by status if a filter is applied
if ($statusFilter !== 'all') {
    $filteredWarrants = [];
    
    foreach ($warrants as $warrant) {
        if ($warrant['status'] === $statusFilter) {
            $filteredWarrants[] = $warrant;
        }
    }
    
    $warrants = $filteredWarrants;
}

// Sort warrants by issue date (newest first)
usort($warrants, function($a, $b) {
    return strtotime($b['issue_date']) - strtotime($a['issue_date']);
});

// Check creation permission
$canCreateWarrant = currentUserCan('warrants', 'create');
$canEditWarrant = currentUserCan('warrants', 'edit');
$canDeleteWarrant = currentUserCan('warrants', 'delete');

// Get user roles to determine if execution is allowed
$isTrainee = hasUserRole('Trainee');
$isStudent = hasUserRole('Student');
$isPresident = hasUserRole('President');
$isSecretary = hasUserRole('Secretary');

// Check if user can execute warrants (not trainee, student, president or secretary)
$canExecuteWarrant = !$isTrainee && !$isStudent && !$isPresident && !$isSecretary && $canEditWarrant;
?>

<main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Haftbefehle</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group mr-2">
                <a href="?status=all" class="btn btn-sm <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Alle
                </a>
                <a href="?status=open" class="btn btn-sm <?php echo $statusFilter === 'open' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Offen
                </a>
                <a href="?status=executed" class="btn btn-sm <?php echo $statusFilter === 'executed' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Vollstreckt
                </a>
            </div>
            
            <?php if ($canCreateWarrant): ?>
            <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#createWarrantModal">
                <span data-feather="plus"></span>
                Neuer Haftbefehl
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!empty($message)): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if (empty($warrants)): ?>
    <div class="alert alert-info">
        <?php if ($statusFilter === 'all'): ?>
            Keine Haftbefehle vorhanden.
        <?php elseif ($statusFilter === 'open'): ?>
            Keine offenen Haftbefehle vorhanden.
        <?php elseif ($statusFilter === 'executed'): ?>
            Keine vollstreckten Haftbefehle vorhanden.
        <?php endif; ?>
    </div>
    <?php else: ?>
    
    <div class="table-responsive">
        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>Name des Beschuldigten</th>
                    <th>Aktenzeichen</th>
                    <th>Ausstellungsdatum</th>
                    <th>Status</th>
                    <th>Ausgestellt von</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($warrants as $warrant): ?>
                <tr>
                    <td><?php echo isset($warrant['suspect']) ? htmlspecialchars($warrant['suspect']) : htmlspecialchars($warrant['subject']); ?></td>
                    <td><?php echo htmlspecialchars($warrant['case_number']); ?></td>
                    <td><?php echo formatDate($warrant['issue_date']); ?></td>
                    <td>
                        <?php if ($warrant['status'] === 'open'): ?>
                            <span class="badge badge-warning">Offen</span>
                        <?php else: ?>
                            <span class="badge badge-success">Vollstreckt</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($warrant['creator_name']); ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-info view-warrant-btn" 
                                data-id="<?php echo $warrant['id']; ?>"
                                data-subject="<?php echo isset($warrant['suspect']) ? htmlspecialchars($warrant['suspect']) : htmlspecialchars($warrant['subject']); ?>"
                                data-case-number="<?php echo htmlspecialchars($warrant['case_number']); ?>"
                                data-issue-date="<?php echo htmlspecialchars($warrant['issue_date']); ?>"
                                data-status="<?php echo htmlspecialchars($warrant['status']); ?>"
                                data-creator="<?php echo htmlspecialchars($warrant['creator_name']); ?>"
                                data-executed-by="<?php echo isset($warrant['executed_by']) ? htmlspecialchars(getUserFullName($warrant['executed_by'])) : ''; ?>"
                                data-executed-date="<?php echo isset($warrant['executed_date']) ? htmlspecialchars(formatDateTime($warrant['executed_date'])) : ''; ?>"
                                data-execution-notes="<?php echo isset($warrant['execution_notes']) ? htmlspecialchars($warrant['execution_notes']) : ''; ?>"
                                data-file="<?php echo !empty($warrant['file']) ? '../uploads/warrants/' . htmlspecialchars($warrant['file']) : ''; ?>"
                                data-toggle="modal" data-target="#viewWarrantModal">
                            <span data-feather="eye"></span>
                        </button>
                        
                        <?php if ($canExecuteWarrant && $warrant['status'] === 'open'): ?>
                        <button type="button" class="btn btn-sm btn-success execute-warrant-btn"
                                data-id="<?php echo $warrant['id']; ?>"
                                data-subject="<?php echo isset($warrant['suspect']) ? htmlspecialchars($warrant['suspect']) : htmlspecialchars($warrant['subject']); ?>"
                                data-toggle="modal" data-target="#executeWarrantModal">
                            <span data-feather="check"></span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($canDeleteWarrant): ?>
                        <button type="button" class="btn btn-sm btn-danger delete-warrant-btn"
                                data-id="<?php echo $warrant['id']; ?>"
                                data-subject="<?php echo isset($warrant['suspect']) ? htmlspecialchars($warrant['suspect']) : htmlspecialchars($warrant['subject']); ?>"
                                data-toggle="modal" data-target="#deleteWarrantModal">
                            <span data-feather="trash-2"></span>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<!-- Create Warrant Modal -->
<?php if ($canCreateWarrant): ?>
<div class="modal fade" id="createWarrantModal" tabindex="-1" role="dialog" aria-labelledby="createWarrantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="createWarrantModalLabel">Neuen Haftbefehl erstellen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="suspect">Name des Beschuldigten*</label>
                        <input type="text" class="form-control" id="suspect" name="suspect" required 
                               value="<?php echo isset($formData['suspect']) ? htmlspecialchars($formData['suspect']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="case_number">Aktenzeichen</label>
                        <input type="text" class="form-control" id="case_number" name="case_number" 
                               value="<?php echo isset($formData['case_number']) ? htmlspecialchars($formData['case_number']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="issue_date">Ausstellungsdatum</label>
                        <input type="date" class="form-control" id="issue_date" name="issue_date" 
                               value="<?php echo isset($formData['issue_date']) ? htmlspecialchars($formData['issue_date']) : date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="warrant_file">Haftbefehl (Dokument)</label>
                        <input type="file" class="form-control-file" id="warrant_file" name="warrant_file">
                        <small class="form-text text-muted">Hochladen des unterschriebenen Haftbefehls als Bild (JPG, JPEG, PNG)</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- View Warrant Modal -->
<div class="modal fade" id="viewWarrantModal" tabindex="-1" role="dialog" aria-labelledby="viewWarrantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewWarrantModalLabel">Haftbefehl ansehen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Name des Beschuldigten</h6>
                        <p id="view-subject"></p>
                        
                        <h6>Aktenzeichen</h6>
                        <p id="view-case-number"></p>
                        
                        <h6>Ausstellungsdatum</h6>
                        <p id="view-issue-date"></p>
                        
                        <h6>Status</h6>
                        <p id="view-status"></p>
                        
                        <h6>Ausgestellt von</h6>
                        <p id="view-creator"></p>
                        
                        <div id="execution-details" style="display: none;">
                            <h6>Vollstreckt von</h6>
                            <p id="view-executed-by"></p>
                            
                            <h6>Vollstreckungsdatum</h6>
                            <p id="view-executed-date"></p>
                            
                            <h6>Notizen zur Vollstreckung</h6>
                            <p id="view-execution-notes"></p>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div id="warrant-file-container" style="display: none;">
                            <h6>Haftbefehl (Dokument)</h6>
                            <div class="text-center">
                                <img id="warrant-file-img" src="" alt="Haftbefehl Dokument" class="img-fluid border">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Execute Warrant Modal -->
<?php if ($canExecuteWarrant): ?>
<div class="modal fade" id="executeWarrantModal" tabindex="-1" role="dialog" aria-labelledby="executeWarrantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="warrant_id" id="execute-warrant-id">
                <input type="hidden" name="status" value="executed">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="executeWarrantModalLabel">Haftbefehl vollstrecken</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie den Haftbefehl <strong id="execute-warrant-subject"></strong> als vollstreckt markieren möchten?</p>
                    
                    <div class="form-group">
                        <label for="execution_notes">Notizen zur Vollstreckung</label>
                        <textarea class="form-control" id="execution_notes" name="execution_notes" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Als vollstreckt markieren</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Delete Warrant Modal -->
<?php if ($canDeleteWarrant): ?>
<div class="modal fade" id="deleteWarrantModal" tabindex="-1" role="dialog" aria-labelledby="deleteWarrantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="" method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="warrant_id" id="delete-warrant-id">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteWarrantModalLabel">Haftbefehl löschen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <p>Sind Sie sicher, dass Sie den Haftbefehl <strong id="delete-warrant-subject"></strong> löschen möchten?</p>
                    <p class="text-danger">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Update view warrant modal with data
document.addEventListener('DOMContentLoaded', function() {
    // View warrant modal
    const viewButtons = document.querySelectorAll('.view-warrant-btn');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Set modal content from data attributes
            document.getElementById('view-subject').textContent = this.getAttribute('data-subject');
            document.getElementById('view-case-number').textContent = this.getAttribute('data-case-number') || 'Nicht angegeben';
            document.getElementById('view-issue-date').textContent = this.getAttribute('data-issue-date');
            
            const status = this.getAttribute('data-status');
            if (status === 'open') {
                document.getElementById('view-status').innerHTML = '<span class="badge badge-warning">Offen</span>';
                document.getElementById('execution-details').style.display = 'none';
            } else {
                document.getElementById('view-status').innerHTML = '<span class="badge badge-success">Vollstreckt</span>';
                document.getElementById('view-executed-by').textContent = this.getAttribute('data-executed-by');
                document.getElementById('view-executed-date').textContent = this.getAttribute('data-executed-date');
                document.getElementById('view-execution-notes').textContent = this.getAttribute('data-execution-notes') || 'Keine Notizen';
                document.getElementById('execution-details').style.display = 'block';
            }
            
            document.getElementById('view-creator').textContent = this.getAttribute('data-creator');
            
            // Handle file display
            const fileUrl = this.getAttribute('data-file');
            if (fileUrl) {
                document.getElementById('warrant-file-img').src = fileUrl;
                document.getElementById('warrant-file-container').style.display = 'block';
            } else {
                document.getElementById('warrant-file-container').style.display = 'none';
            }
        });
    });
    
    // Execute warrant modal
    const executeButtons = document.querySelectorAll('.execute-warrant-btn');
    executeButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('execute-warrant-id').value = this.getAttribute('data-id');
            document.getElementById('execute-warrant-subject').textContent = this.getAttribute('data-subject');
        });
    });
    
    // Delete warrant modal
    const deleteButtons = document.querySelectorAll('.delete-warrant-btn');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('delete-warrant-id').value = this.getAttribute('data-id');
            document.getElementById('delete-warrant-subject').textContent = this.getAttribute('data-subject');
        });
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>