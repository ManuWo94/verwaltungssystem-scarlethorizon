<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Nur Admins dürfen Fristen verwalten
checkPermissionOrDie('admin', 'view');

$message = '';
$error = '';

// AJAX Handler für CRUD-Operationen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $limitations = loadJsonData('limitations.json');
    
    if ($action === 'create') {
        checkPermissionOrDie('admin', 'create');
        
        $newLimitation = [
            'id' => 'limitation_' . uniqid(),
            'label' => sanitize($_POST['label'] ?? ''),
            'days' => intval($_POST['days'] ?? 0)
        ];
        
        if (empty($newLimitation['label']) || $newLimitation['days'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'Bitte füllen Sie alle Felder korrekt aus.']);
            exit;
        }
        
        $limitations[] = $newLimitation;
        
        if (saveJsonData('limitations.json', $limitations)) {
            echo json_encode(['success' => true, 'message' => 'Frist erfolgreich erstellt.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern.']);
        }
        exit;
    }
    
    if ($action === 'update') {
        checkPermissionOrDie('admin', 'edit');
        
        $id = $_POST['id'] ?? '';
        $label = sanitize($_POST['label'] ?? '');
        $days = intval($_POST['days'] ?? 0);
        
        if (empty($label) || $days <= 0) {
            echo json_encode(['success' => false, 'message' => 'Bitte füllen Sie alle Felder korrekt aus.']);
            exit;
        }
        
        $found = false;
        foreach ($limitations as &$lim) {
            if ($lim['id'] === $id) {
                $lim['label'] = $label;
                $lim['days'] = $days;
                $found = true;
                break;
            }
        }
        unset($lim);
        
        if ($found && saveJsonData('limitations.json', $limitations)) {
            echo json_encode(['success' => true, 'message' => 'Frist erfolgreich aktualisiert.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Aktualisieren.']);
        }
        exit;
    }
    
    if ($action === 'delete') {
        checkPermissionOrDie('admin', 'delete');
        
        $id = $_POST['id'] ?? '';
        $limitations = array_filter($limitations, function($lim) use ($id) {
            return $lim['id'] !== $id;
        });
        
        if (saveJsonData('limitations.json', array_values($limitations))) {
            echo json_encode(['success' => true, 'message' => 'Frist gelöscht.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen.']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.']);
    exit;
}

// Normale Seite laden
require_once '../includes/header.php';
$limitations = loadJsonData('limitations.json');
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Verjährungsfristen verwalten</h1>
                <button class="btn btn-primary" data-toggle="modal" data-target="#addLimitationModal">
                    <i data-feather="plus"></i> Neue Frist
                </button>
            </div>

            <div id="messageContainer"></div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Bezeichnung</th>
                                    <th>Tage</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="limitationsTable">
                                <?php foreach ($limitations as $lim): ?>
                                <tr data-id="<?php echo htmlspecialchars($lim['id']); ?>">
                                    <td><?php echo htmlspecialchars($lim['label']); ?></td>
                                    <td><?php echo htmlspecialchars($lim['days']); ?> Tage</td>
                                    <td>
                                        <button class="btn btn-sm btn-primary btn-edit" data-id="<?php echo htmlspecialchars($lim['id']); ?>" data-label="<?php echo htmlspecialchars($lim['label']); ?>" data-days="<?php echo htmlspecialchars($lim['days']); ?>">
                                            <i data-feather="edit"></i> Bearbeiten
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-delete" data-id="<?php echo htmlspecialchars($lim['id']); ?>">
                                            <i data-feather="trash-2"></i> Löschen
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal: Neue Frist -->
<div class="modal fade" id="addLimitationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Neue Verjährungsfrist</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addLimitationForm">
                    <div class="form-group">
                        <label>Bezeichnung *</label>
                        <input type="text" class="form-control" id="add_label" name="label" required placeholder="z.B. Standard 21 Tage">
                    </div>
                    <div class="form-group">
                        <label>Tage *</label>
                        <input type="number" class="form-control" id="add_days" name="days" required min="1" placeholder="z.B. 21">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="btnSaveNew">Speichern</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Frist bearbeiten -->
<div class="modal fade" id="editLimitationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verjährungsfrist bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editLimitationForm">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="form-group">
                        <label>Bezeichnung *</label>
                        <input type="text" class="form-control" id="edit_label" name="label" required>
                    </div>
                    <div class="form-group">
                        <label>Tage *</label>
                        <input type="number" class="form-control" id="edit_days" name="days" required min="1">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="btnSaveEdit">Speichern</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    feather.replace();
    
    function showMessage(message, type = 'success') {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        $('#messageContainer').html(`<div class="alert ${alertClass} alert-dismissible fade show"><button type="button" class="close" data-dismiss="alert">&times;</button>${message}</div>`);
        setTimeout(() => $('#messageContainer').empty(), 3000);
    }
    
    // Neue Frist speichern
    $('#btnSaveNew').on('click', function() {
        const formData = new FormData($('#addLimitationForm')[0]);
        formData.append('ajax', '1');
        formData.append('action', 'create');
        
        $.post('limitations.php', Object.fromEntries(formData), function(response) {
            if (response.success) {
                showMessage(response.message, 'success');
                $('#addLimitationModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage(response.message, 'error');
            }
        }, 'json');
    });
    
    // Frist bearbeiten - Modal öffnen
    $(document).on('click', '.btn-edit', function() {
        const id = $(this).data('id');
        const label = $(this).data('label');
        const days = $(this).data('days');
        
        $('#edit_id').val(id);
        $('#edit_label').val(label);
        $('#edit_days').val(days);
        $('#editLimitationModal').modal('show');
    });
    
    // Frist bearbeiten - Speichern
    $('#btnSaveEdit').on('click', function() {
        const formData = new FormData($('#editLimitationForm')[0]);
        formData.append('ajax', '1');
        formData.append('action', 'update');
        
        $.post('limitations.php', Object.fromEntries(formData), function(response) {
            if (response.success) {
                showMessage(response.message, 'success');
                $('#editLimitationModal').modal('hide');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage(response.message, 'error');
            }
        }, 'json');
    });
    
    // Frist löschen
    $(document).on('click', '.btn-delete', function() {
        if (!confirm('Wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
            return;
        }
        
        const id = $(this).data('id');
        
        $.post('limitations.php', { ajax: '1', action: 'delete', id: id }, function(response) {
            if (response.success) {
                showMessage(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage(response.message, 'error');
            }
        }, 'json');
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
