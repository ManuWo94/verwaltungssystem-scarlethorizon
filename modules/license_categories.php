<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/auth.php';

// Nur Admins
requireLogin();
if (!currentUserCan('admin', 'view') && $_SESSION['role'] !== 'Administrator') {
    header('Location: ' . getBasePath() . 'access_denied.php');
    exit;
}

$basePath = getBasePath();
$categories = loadJsonData('license_categories.json');

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Kategorie erstellen
    if ($action === 'create') {
        $newCategory = [
            'id' => 'cat_' . uniqid(),
            'name' => $_POST['name'] ?? '',
            'active' => true,
            'number_schema' => $_POST['number_schema'] ?? '',
            'default_duration_days' => intval($_POST['default_duration_days'] ?? 365),
            'notification_enabled' => isset($_POST['notification_enabled']),
            'notification_days_before' => intval($_POST['notification_days_before'] ?? 7),
            'template' => $_POST['template'] ?? '',
            'fields' => json_decode($_POST['fields'] ?? '[]', true),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['username']
        ];
        
        $categories[] = $newCategory;
        
        if (saveJsonData('license_categories.json', $categories)) {
            echo json_encode(['success' => true, 'message' => 'Kategorie erstellt']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Speicherfehler']);
        }
        exit;
    }
    
    // Kategorie bearbeiten
    if ($action === 'update') {
        $categoryId = $_POST['category_id'] ?? '';
        
        foreach ($categories as &$cat) {
            if ($cat['id'] === $categoryId) {
                $cat['name'] = $_POST['name'] ?? $cat['name'];
                $cat['number_schema'] = $_POST['number_schema'] ?? $cat['number_schema'];
                $cat['default_duration_days'] = intval($_POST['default_duration_days'] ?? $cat['default_duration_days']);
                $cat['notification_enabled'] = isset($_POST['notification_enabled']);
                $cat['notification_days_before'] = intval($_POST['notification_days_before'] ?? $cat['notification_days_before']);
                $cat['template'] = $_POST['template'] ?? $cat['template'];
                $cat['fields'] = json_decode($_POST['fields'] ?? '[]', true);
                break;
            }
        }
        
        if (saveJsonData('license_categories.json', $categories)) {
            echo json_encode(['success' => true, 'message' => 'Kategorie aktualisiert']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Speicherfehler']);
        }
        exit;
    }
    
    // Kategorie aktivieren/deaktivieren
    if ($action === 'toggle_active') {
        $categoryId = $_POST['category_id'] ?? '';
        
        foreach ($categories as &$cat) {
            if ($cat['id'] === $categoryId) {
                $cat['active'] = !($cat['active'] ?? true);
                break;
            }
        }
        
        if (saveJsonData('license_categories.json', $categories)) {
            echo json_encode(['success' => true, 'message' => 'Status geändert']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Speicherfehler']);
        }
        exit;
    }
}

?>

<div class="container-fluid">
    <div class="row">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i data-feather="settings"></i> Lizenzkategorien verwalten
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo $basePath; ?>modules/licenses.php" class="btn btn-sm btn-outline-secondary mr-2">
                        <i data-feather="arrow-left"></i> Zurück zu Lizenzen
                    </a>
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#createCategoryModal">
                        <i data-feather="plus"></i> Neue Kategorie
                    </button>
                </div>
            </div>

            <!-- Hilfetext -->
            <div class="alert alert-info">
                <h6><i data-feather="info"></i> Kategorien verwalten</h6>
                <p class="mb-0">
                    Hier können Sie Lizenzkategorien erstellen und bearbeiten. Jede Kategorie definiert:
                </p>
                <ul class="mb-0">
                    <li><strong>Lizenznummern-Schema:</strong> z.B. BL-{YEAR}-{NUM:4} → BL-2026-0001</li>
                    <li><strong>Standardlaufzeit:</strong> Wie lange die Lizenz standardmäßig gültig ist</li>
                    <li><strong>Textvorlage:</strong> Der Lizenztext mit Platzhaltern</li>
                    <li><strong>Felder:</strong> Welche Daten beim Erstellen abgefragt werden</li>
                </ul>
            </div>

            <!-- Kategorien Liste -->
            <div class="card">
                <div class="card-header">
                    <strong>Kategorien (<?php echo count($categories); ?>)</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Nummern-Schema</th>
                                    <th>Laufzeit (Tage)</th>
                                    <th>Felder</th>
                                    <th>Status</th>
                                    <th>Erstellt</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Keine Kategorien vorhanden
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                    <td><code><?php echo htmlspecialchars($cat['number_schema']); ?></code></td>
                                    <td><?php echo $cat['default_duration_days']; ?> Tage</td>
                                    <td><?php echo count($cat['fields'] ?? []); ?> Felder</td>
                                    <td>
                                        <?php if ($cat['active'] ?? true): ?>
                                        <span class="badge badge-success">Aktiv</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">Inaktiv</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($cat['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary edit-category" 
                                                data-category='<?php echo json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                            <i data-feather="edit-2"></i>
                                        </button>
                                        <button class="btn btn-sm btn-<?php echo ($cat['active'] ?? true) ? 'warning' : 'success'; ?> toggle-category" 
                                                data-category-id="<?php echo htmlspecialchars($cat['id']); ?>"
                                                data-active="<?php echo ($cat['active'] ?? true) ? '1' : '0'; ?>">
                                            <i data-feather="<?php echo ($cat['active'] ?? true) ? 'eye-off' : 'eye'; ?>"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Platzhalter Referenz -->
            <div class="card mt-4">
                <div class="card-header">
                    <strong>Verfügbare Platzhalter</strong>
                </div>
                <div class="card-body">
                    <h6>Nummern-Schema:</h6>
                    <ul>
                        <li><code>{YEAR}</code> - Aktuelles Jahr (z.B. 2026)</li>
                        <li><code>{NUM:X}</code> - Fortlaufende Nummer mit X Stellen (z.B. {NUM:4} → 0001)</li>
                    </ul>
                    
                    <h6 class="mt-3">Textvorlage (Systemfelder):</h6>
                    <ul>
                        <li><code>{LICENSE_NUMBER}</code> - Generierte Lizenznummer</li>
                        <li><code>{START_DATE}</code> - Startdatum</li>
                        <li><code>{END_DATE}</code> - Ablaufdatum</li>
                        <li><code>{ISSUE_DATE}</code> - Ausstellungsdatum (heute)</li>
                        <li><code>{ISSUER_NAME}</code> - Name des Erstellers</li>
                        <li><code>{ISSUER_ROLE}</code> - Rolle des Erstellers (ohne Klammern)</li>
                    </ul>
                    
                    <h6 class="mt-3">Benutzerdefinierte Felder:</h6>
                    <p>Felder die Sie definieren werden als <code>{FELDNAME}</code> verfügbar (z.B. <code>{HOLDER_NAME}</code>)</p>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal: Kategorie erstellen/bearbeiten -->
<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Kategorie erstellen</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="categoryForm">
                    <input type="hidden" name="action" id="categoryAction" value="create">
                    <input type="hidden" name="category_id" id="categoryId">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="categoryName">Name *</label>
                                <input type="text" class="form-control" name="name" id="categoryName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="numberSchema">Nummern-Schema *</label>
                                <input type="text" class="form-control" name="number_schema" id="numberSchema" 
                                       placeholder="z.B. BL-{YEAR}-{NUM:4}" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="defaultDuration">Standardlaufzeit (Tage) *</label>
                                <input type="number" class="form-control" name="default_duration_days" id="defaultDuration" 
                                       min="1" value="365" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox mt-4">
                                    <input type="checkbox" class="custom-control-input" name="notification_enabled" 
                                           id="notificationEnabled" checked>
                                    <label class="custom-control-label" for="notificationEnabled">
                                        Benachrichtigung aktivieren
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="notificationDays">Benachrichtigung (Tage vorher)</label>
                                <input type="number" class="form-control" name="notification_days_before" 
                                       id="notificationDaysBefore" min="1" value="7">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="categoryTemplate">Textvorlage *</label>
                        <textarea class="form-control" name="template" id="categoryTemplate" rows="10" required></textarea>
                        <small class="form-text text-muted">Verwenden Sie Platzhalter wie {LICENSE_NUMBER}, {HOLDER_NAME}, etc.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Felder definieren</label>
                        <div id="fieldsContainer"></div>
                        <button type="button" class="btn btn-sm btn-secondary" id="addField">
                            <i data-feather="plus"></i> Feld hinzufügen
                        </button>
                    </div>
                    
                    <input type="hidden" name="fields" id="fieldsJson">
                    
                    <button type="submit" class="btn btn-primary">
                        <i data-feather="save"></i> Speichern
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Erstellen (Alias) -->
<div class="modal fade" id="createCategoryModal"></div>

<script>
$(document).ready(function() {
    feather.replace();
    
    let fields = [];
    
    // Felder rendern
    function renderFields() {
        let html = '';
        fields.forEach((field, index) => {
            html += '<div class="card mb-2">';
            html += '<div class="card-body">';
            html += '<div class="row">';
            html += '<div class="col-md-3">';
            html += '<input type="text" class="form-control form-control-sm" placeholder="Feldname (z.B. HOLDER_NAME)" value="' + field.name + '" data-index="' + index + '" data-prop="name">';
            html += '</div>';
            html += '<div class="col-md-3">';
            html += '<input type="text" class="form-control form-control-sm" placeholder="Label (z.B. Inhabername)" value="' + field.label + '" data-index="' + index + '" data-prop="label">';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<select class="form-control form-control-sm" data-index="' + index + '" data-prop="type">';
            html += '<option value="text"' + (field.type === 'text' ? ' selected' : '') + '>Text</option>';
            html += '<option value="date"' + (field.type === 'date' ? ' selected' : '') + '>Datum</option>';
            html += '<option value="number"' + (field.type === 'number' ? ' selected' : '') + '>Nummer</option>';
            html += '<option value="select"' + (field.type === 'select' ? ' selected' : '') + '>Auswahl</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<div class="custom-control custom-checkbox">';
            html += '<input type="checkbox" class="custom-control-input" id="req_' + index + '" data-index="' + index + '" data-prop="required"' + (field.required ? ' checked' : '') + '>';
            html += '<label class="custom-control-label" for="req_' + index + '">Pflichtfeld</label>';
            html += '</div>';
            html += '</div>';
            html += '<div class="col-md-2 text-right">';
            html += '<button type="button" class="btn btn-sm btn-danger remove-field" data-index="' + index + '"><i data-feather="trash-2"></i></button>';
            html += '</div>';
            html += '</div>';
            
            if (field.type === 'select') {
                html += '<div class="row mt-2">';
                html += '<div class="col-md-12">';
                html += '<input type="text" class="form-control form-control-sm" placeholder="Optionen (kommagetrennt)" value="' + (field.options ? field.options.join(', ') : '') + '" data-index="' + index + '" data-prop="options">';
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            html += '</div>';
        });
        $('#fieldsContainer').html(html);
        feather.replace();
    }
    
    // Feld hinzufügen
    $('#addField').click(function() {
        fields.push({
            name: '',
            label: '',
            type: 'text',
            required: false
        });
        renderFields();
    });
    
    // Feld entfernen
    $(document).on('click', '.remove-field', function() {
        const index = $(this).data('index');
        fields.splice(index, 1);
        renderFields();
    });
    
    // Feldwerte aktualisieren
    $(document).on('change keyup', '#fieldsContainer input, #fieldsContainer select', function() {
        const index = $(this).data('index');
        const prop = $(this).data('prop');
        
        if (prop === 'required') {
            fields[index][prop] = $(this).is(':checked');
        } else if (prop === 'options') {
            fields[index][prop] = $(this).val().split(',').map(s => s.trim()).filter(s => s);
        } else {
            fields[index][prop] = $(this).val();
        }
    });
    
    // Kategorie erstellen Modal öffnen
    $('#createCategoryModal').on('show.bs.modal', function() {
        $('#categoryModal').modal('show');
    });
    
    // Modal zurücksetzen
    $('#categoryModal').on('show.bs.modal', function() {
        $('#categoryForm')[0].reset();
        $('#categoryAction').val('create');
        $('#categoryId').val('');
        $('#categoryModalTitle').text('Kategorie erstellen');
        fields = [];
        renderFields();
    });
    
    // Kategorie bearbeiten
    $('.edit-category').click(function() {
        const category = $(this).data('category');
        
        $('#categoryAction').val('update');
        $('#categoryId').val(category.id);
        $('#categoryModalTitle').text('Kategorie bearbeiten');
        $('#categoryName').val(category.name);
        $('#numberSchema').val(category.number_schema);
        $('#defaultDuration').val(category.default_duration_days);
        $('#notificationEnabled').prop('checked', category.notification_enabled);
        $('#notificationDaysBefore').val(category.notification_days_before);
        $('#categoryTemplate').val(category.template);
        
        fields = category.fields || [];
        renderFields();
        
        $('#categoryModal').modal('show');
    });
    
    // Formular absenden
    $('#categoryForm').submit(function(e) {
        e.preventDefault();
        
        $('#fieldsJson').val(JSON.stringify(fields));
        
        $.post('', $(this).serialize(), function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        }, 'json');
    });
    
    // Kategorie aktivieren/deaktivieren
    $('.toggle-category').click(function() {
        const categoryId = $(this).data('category-id');
        const isActive = $(this).data('active') === 1;
        
        if (!confirm('Kategorie ' + (isActive ? 'deaktivieren' : 'aktivieren') + '?')) return;
        
        $.post('', {
            action: 'toggle_active',
            category_id: categoryId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        }, 'json');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
