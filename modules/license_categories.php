<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Nur Admins
checkLogin();
if (!currentUserCan('admin', 'view') && $_SESSION['role'] !== 'Administrator') {
    header('Location: ' . getBasePath() . 'access_denied.php');
    exit;
}

$basePath = getBasePath();
$categories = loadJsonData('license_categories.json');

// AJAX Handler - VOR dem Header!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Kategorie erstellen
    if ($action === 'create') {
        try {
            $fieldsData = json_decode($_POST['fields'] ?? '[]', true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['success' => false, 'message' => 'Ungültige Felddaten: ' . json_last_error_msg()]);
                exit;
            }
            
            $newCategory = [
                'id' => 'cat_' . uniqid(),
                'name' => $_POST['name'] ?? '',
                'active' => true,
                'number_schema' => $_POST['number_schema'] ?? '',
                'default_duration_days' => intval($_POST['default_duration_days'] ?? 365),
                'notification_enabled' => isset($_POST['notification_enabled']),
                'notification_days_before' => intval($_POST['notification_days_before'] ?? 7),
                'template' => $_POST['template'] ?? '',
                'fields' => $fieldsData,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['username']
            ];
            
            $categories[] = $newCategory;
            
            if (saveJsonData('license_categories.json', $categories)) {
                echo json_encode(['success' => true, 'message' => 'Kategorie erstellt']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Datei']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Kategorie bearbeiten
    if ($action === 'update') {
        try {
            $categoryId = $_POST['category_id'] ?? '';
            $fieldsData = json_decode($_POST['fields'] ?? '[]', true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['success' => false, 'message' => 'Ungültige Felddaten: ' . json_last_error_msg()]);
                exit;
            }
            
            foreach ($categories as &$cat) {
                if ($cat['id'] === $categoryId) {
                    $cat['name'] = $_POST['name'] ?? $cat['name'];
                    $cat['number_schema'] = $_POST['number_schema'] ?? $cat['number_schema'];
                    $cat['default_duration_days'] = intval($_POST['default_duration_days'] ?? $cat['default_duration_days']);
                    $cat['notification_enabled'] = isset($_POST['notification_enabled']);
                    $cat['notification_days_before'] = intval($_POST['notification_days_before'] ?? $cat['notification_days_before']);
                    $cat['template'] = $_POST['template'] ?? $cat['template'];
                    $cat['fields'] = $fieldsData;
                    break;
                }
            }
            
            if (saveJsonData('license_categories.json', $categories)) {
                echo json_encode(['success' => true, 'message' => 'Kategorie aktualisiert']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Datei']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
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

// Header nur laden wenn es kein AJAX-Request war
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
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
                                <label>Nummern-Schema *</label>
                                <input type="hidden" name="number_schema" id="numberSchema" required>
                                
                                <!-- PREFIX Eingabe -->
                                <div class="form-group mb-2">
                                    <label for="schemaPrefix" class="small text-muted">Präfix (z.B. "LIC", "WL", "BL")</label>
                                    <input type="text" class="form-control form-control-sm" id="schemaPrefix" placeholder="z.B. LIC" maxlength="10">
                                    <small class="text-muted">Wird als PREFIX-Baustein im Schema verwendet</small>
                                </div>
                                
                                <!-- Drag & Drop Builder -->
                                <div class="card">
                                    <div class="card-header bg-light py-2">
                                        <small class="text-muted font-weight-bold">Bausteine (ziehen Sie die Elemente in die Drop-Zone)</small>
                                    </div>
                                    <div class="card-body p-2">
                                        <div class="d-flex flex-wrap gap-2" id="schemaBlocks" style="gap: 8px;">
                                            <button type="button" class="btn btn-sm btn-primary schema-block-btn" data-value="PREFIX" style="cursor: move; font-size: 13px;">
                                                📝 PREFIX
                                            </button>
                                            <span class="badge badge-info schema-block" draggable="true" data-value="{YEAR}" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                📅 JAHR
                                            </span>
                                            <span class="badge badge-warning schema-block" draggable="true" data-value="{MONTH}" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                📆 MONAT
                                            </span>
                                            <span class="badge badge-success schema-block" draggable="true" data-value="{NUM:3}" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                🔢 NR (3)
                                            </span>
                                            <span class="badge badge-success schema-block" draggable="true" data-value="{NUM:4}" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                🔢 NR (4)
                                            </span>
                                            <span class="badge badge-danger schema-block" draggable="true" data-value="{INITIALS}" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                👤 INITIALEN
                                            </span>
                                            <span class="badge badge-secondary schema-block" draggable="true" data-value="-" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                -
                                            </span>
                                            <span class="badge badge-secondary schema-block" draggable="true" data-value="/" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                /
                                            </span>
                                            <span class="badge badge-secondary schema-block" draggable="true" data-value="_" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                                _
                                            </span>
                                        </div>
                                        <small class="text-muted d-block mt-2">💡 Tipp: INITIALEN nimmt Anfangsbuchstaben aus dem Feld "Inhabername"</small>
                                    </div>
                                </div>
                                
                                <!-- Drop Zone -->
                                <div class="mt-2 p-3 border rounded" id="schemaDropZone" 
                                     style="min-height: 60px; background: #f8f9fa; border: 2px dashed #dee2e6 !important;">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">Ziehen Sie Bausteine hierher...</small>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="resetSchema" title="Schema zurücksetzen">
                                            <i data-feather="x"></i>
                                        </button>
                                    </div>
                                    <div id="schemaPreview" class="d-flex flex-wrap align-items-center" style="gap: 4px; min-height: 30px;"></div>
                                </div>
                                
                                <!-- Live Vorschau -->
                                <div class="mt-2 d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Vorschau:</small>
                                    <code id="schemaOutput" class="flex-grow-1 ml-2 p-2 bg-white border rounded">PREFIX-{YEAR}-{NUM:3}</code>
                                </div>
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
                        <label>Textvorlage *</label>
                        
                        <!-- Platzhalter Bausteine -->
                        <div class="card mb-2">
                            <div class="card-header bg-light py-2">
                                <small class="text-muted font-weight-bold">Verfügbare Platzhalter (klicken oder ziehen)</small>
                            </div>
                            <div class="card-body p-2">
                                <div class="d-flex flex-wrap" style="gap: 6px;" id="templatePlaceholders">
                                    <button type="button" class="btn btn-sm btn-outline-primary placeholder-btn" data-placeholder="{LICENSE_NUMBER}">Lizenznummer</button>
                                    <button type="button" class="btn btn-sm btn-outline-info placeholder-btn" data-placeholder="{START_DATE}">Startdatum</button>
                                    <button type="button" class="btn btn-sm btn-outline-info placeholder-btn" data-placeholder="{END_DATE}">Enddatum</button>
                                    <button type="button" class="btn btn-sm btn-outline-info placeholder-btn" data-placeholder="{ISSUE_DATE}">Ausstellungsdatum</button>
                                    <button type="button" class="btn btn-sm btn-outline-success placeholder-btn" data-placeholder="{ISSUER_NAME}">Ersteller Name</button>
                                    <button type="button" class="btn btn-sm btn-outline-success placeholder-btn" data-placeholder="{ISSUER_ROLE}">Ersteller Rolle</button>
                                    <button type="button" class="btn btn-sm btn-outline-warning placeholder-btn" id="customFieldPlaceholder" disabled>
                                        <small>Eigene Felder unten definieren →</small>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <textarea class="form-control" name="template" id="categoryTemplate" rows="10" required placeholder="Klicken Sie auf Platzhalter oben, um sie hier einzufügen..."></textarea>
                        <small class="form-text text-muted">Klicken Sie auf die Buttons oben, um Platzhalter einzufügen.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Felder definieren</label>
                        <p class="text-muted small mb-2">Häufig verwendete Felder schnell hinzufügen:</p>
                        
                        <!-- Quick-Add Standard-Felder -->
                        <div class="card mb-2 bg-light">
                            <div class="card-body p-2">
                                <div class="d-flex flex-wrap" style="gap: 4px;">
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"HOLDER_NAME","label":"Inhabername","type":"text","required":true}'>
                                        + Inhabername
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"TG_NUMBER","label":"TG-Nummer","type":"text","required":true}'>
                                        + TG-Nummer
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"BUSINESS_TYPE","label":"Geschäftstyp","type":"select","required":true,"options":["Bar","Restaurant","Club","Geschäft","Sonstiges"]}'>
                                        + Geschäftstyp
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"BUSINESS_NAME","label":"Gewerbename","type":"text","required":true}'>
                                        + Gewerbename
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"WEAPON_NAME","label":"Waffenname","type":"text","required":true}'>
                                        + Waffenname
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"SERIAL_NUMBER","label":"Seriennummer","type":"text","required":true}'>
                                        + Seriennummer
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"SUBSTANCE","label":"Substanz","type":"text","required":true}'>
                                        + Substanz
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"ISSUE_DATE","label":"Ausstellungsdatum","type":"date","required":true}'>
                                        + Ausstellungsdatum
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary quick-field-btn" data-field='{"name":"DURATION","label":"Frist (Tage)","type":"number","required":true}'>
                                        + Frist
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary quick-field-btn" data-field='{"name":"CUSTOM","label":"Benutzerdefiniert","type":"text","required":false}'>
                                        + Eigenes Textfeld
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-muted small mb-2">Oder ziehen Sie Feldtypen in die Drop-Zone:</p>
                        
                        <!-- Feld-Bausteine -->
                        <div class="card mb-2">
                            <div class="card-header bg-light py-2">
                                <small class="text-muted font-weight-bold">Feldtypen (ziehen für benutzerdefinierte Felder)</small>
                            </div>
                            <div class="card-body p-2">
                                <div class="d-flex flex-wrap" style="gap: 6px;">
                                    <span class="badge badge-primary field-type-block" draggable="true" data-type="text" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                        📝 Textfeld
                                    </span>
                                    <span class="badge badge-info field-type-block" draggable="true" data-type="date" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                        📅 Datum
                                    </span>
                                    <span class="badge badge-success field-type-block" draggable="true" data-type="number" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                        🔢 Nummer
                                    </span>
                                    <span class="badge badge-warning field-type-block" draggable="true" data-type="select" style="cursor: move; padding: 8px 12px; font-size: 13px;">
                                        📋 Auswahl
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Felder Drop Zone -->
                        <div class="border rounded p-3" id="fieldsDropZone" style="min-height: 100px; background: #f8f9fa; border: 2px dashed #dee2e6 !important;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">Ziehen Sie Feldtypen hierher...</small>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="clearFields" title="Alle Felder löschen">
                                    <i data-feather="trash-2"></i> Zurücksetzen
                                </button>
                            </div>
                            <div id="fieldsContainer"></div>
                        </div>
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

<script>
$(document).ready(function() {
    feather.replace();
    
    let fields = [];
    let schemaElements = [];
    
    // === QUICK-ADD FIELD BUTTONS ===
    
    $(document).on('click', '.quick-field-btn', function(e) {
        e.preventDefault();
        const fieldData = $(this).data('field');
        
        if (fieldData.name === 'CUSTOM') {
            // Benutzerdefiniertes Feld - Prompt für Namen
            const fieldName = prompt('Geben Sie einen Feldnamen ein (z.B. CUSTOM_FIELD):');
            if (!fieldName) return;
            
            const fieldLabel = prompt('Geben Sie einen Anzeigetext ein:');
            if (!fieldLabel) return;
            
            addField('text', fieldName, fieldLabel, false, null);
        } else {
            // Vordefiniertes Feld direkt hinzufügen
            addField(
                fieldData.type,
                fieldData.name,
                fieldData.label,
                fieldData.required || false,
                fieldData.options || null
            );
        }
    });
    
    // === DRAG & DROP SCHEMA BUILDER ===
    
    // Drag Start auf Bausteinen
    $(document).on('dragstart', '.schema-block', function(e) {
        e.originalEvent.dataTransfer.effectAllowed = 'copy';
        e.originalEvent.dataTransfer.setData('text/plain', $(this).data('value'));
    });
    
    // PREFIX Button Click
    $(document).on('click', '.schema-block[data-value="PREFIX"]', function(e) {
        e.preventDefault();
        const prefixValue = $('#schemaPrefix').val().trim();
        if (!prefixValue) {
            alert('Bitte geben Sie einen Präfix-Wert ein (z.B. "LIC" oder "WL")');
            $('#schemaPrefix').focus();
            return;
        }
        schemaElements.push(prefixValue);
        updateSchemaPreview();
    });
    
    // Drop Zone Events
    $('#schemaDropZone').on('dragover', function(e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'copy';
        $(this).addClass('border-primary');
    });
    
    $('#schemaDropZone').on('dragleave', function(e) {
        $(this).removeClass('border-primary');
    });
    
    $('#schemaDropZone').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('border-primary');
        
        const value = e.originalEvent.dataTransfer.getData('text/plain');
        schemaElements.push(value);
        updateSchemaPreview();
    });
    
    // Vorschau aktualisieren
    function updateSchemaPreview() {
        const preview = $('#schemaPreview');
        preview.empty();
        
        if (schemaElements.length === 0) {
            preview.html('<small class="text-muted">Ziehen Sie Bausteine hierher...</small>');
            $('#schemaOutput').text('');
            $('#numberSchema').val('');
            return;
        }
        
        // Elemente anzeigen mit Löschen-Button
        schemaElements.forEach((elem, index) => {
            const displayText = elem.replace('{', '').replace('}', '').replace('NUM:', 'NR-');
            const badge = $('<span class="badge badge-secondary mr-1" style="padding: 6px 10px; font-size: 12px;"></span>')
                .text(displayText)
                .append(' <span style="cursor: pointer; font-weight: bold;" data-index="' + index + '" class="remove-schema-elem">×</span>');
            preview.append(badge);
        });
        
        // Schema zusammenbauen
        const schema = schemaElements.join('');
        $('#schemaOutput').text(schema);
        $('#numberSchema').val(schema);
    }
    
    // Element aus Schema entfernen
    $(document).on('click', '.remove-schema-elem', function() {
        const index = $(this).data('index');
        schemaElements.splice(index, 1);
        updateSchemaPreview();
    });
    
    // Schema komplett zurücksetzen
    $(document).on('click', '#resetSchema', function(e) {
        e.preventDefault();
        e.stopPropagation();
        schemaElements = [];
        updateSchemaPreview();
    });
    
    // === ENDE DRAG & DROP SCHEMA ===
    
    // === PLATZHALTER EINFÜGEN ===
    
    // Platzhalter-Button Click
    $(document).on('click', '.placeholder-btn', function() {
        const placeholder = $(this).data('placeholder');
        const textarea = $('#categoryTemplate')[0];
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        
        // Platzhalter an Cursor-Position einfügen
        const before = text.substring(0, start);
        const after = text.substring(end, text.length);
        textarea.value = before + placeholder + after;
        
        // Cursor nach Platzhalter setzen
        textarea.selectionStart = textarea.selectionEnd = start + placeholder.length;
        textarea.focus();
    });
    
    // === FELDER DRAG & DROP ===
    
    let fieldIdCounter = 0;
    
    // Drag Start auf Feld-Bausteinen
    $(document).on('dragstart', '.field-type-block', function(e) {
        e.originalEvent.dataTransfer.effectAllowed = 'copy';
        e.originalEvent.dataTransfer.setData('text/plain', $(this).data('type'));
    });
    
    // Drop Zone Events für Felder
    $('#fieldsDropZone').on('dragover', function(e) {
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = 'copy';
        $(this).addClass('border-primary');
    });
    
    $('#fieldsDropZone').on('dragleave', function(e) {
        $(this).removeClass('border-primary');
    });
    
    $('#fieldsDropZone').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('border-primary');
        
        const fieldType = e.originalEvent.dataTransfer.getData('text/plain');
        addField(fieldType);
    });
    
    // Feld hinzufügen
    function addField(type = 'text', name = '', label = '', required = false, options = null) {
        const field = {
            id: ++fieldIdCounter,
            name: name || 'FIELD_' + fieldIdCounter,
            label: label || 'Neues Feld',
            type: type,
            required: required,
            options: options || (type === 'select' ? ['Option 1', 'Option 2'] : [])
        };
        
        fields.push(field);
        renderFields();
        updateCustomFieldPlaceholders();
    }
    
    // Alle Felder löschen
    $(document).on('click', '#clearFields', function(e) {
        e.preventDefault();
        if (confirm('Wirklich alle Felder löschen?')) {
            fields = [];
            renderFields();
            updateCustomFieldPlaceholders();
        }
    });
    
    // Eigene Feld-Platzhalter aktualisieren
    function updateCustomFieldPlaceholders() {
        const container = $('#templatePlaceholders');
        // Entferne alte eigene Felder
        container.find('.custom-field-placeholder').remove();
        
        // Füge neue hinzu
        fields.forEach(field => {
            if (field.name) {
                const btn = $('<button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn custom-field-placeholder"></button>')
                    .attr('data-placeholder', '{' + field.name + '}')
                    .text(field.label || field.name);
                container.append(btn);
            }
        });
        
        feather.replace();
    }
    
    // === ENDE FELDER DRAG & DROP ===
    
    // "Neue Kategorie" Button - öffnet das categoryModal direkt
    $(document).on('click', '[data-target="#createCategoryModal"]', function(e) {
        e.preventDefault();
        $('#categoryModal').modal('show');
    });
    
    // Felder rendern
    function renderFields() {
        let html = '';
        
        if (fields.length === 0) {
            html = '<div class="text-center text-muted py-3"><small>Ziehen Sie Feldtypen von oben hierher, um Felder zu erstellen...</small></div>';
        }
        
        fields.forEach((field, index) => {
            // Icon je nach Typ
            let icon = '📝';
            if (field.type === 'date') icon = '📅';
            if (field.type === 'number') icon = '🔢';
            if (field.type === 'select') icon = '📋';
            
            html += '<div class="card mb-2 border-left-primary" style="border-left: 4px solid #007bff !important;">';
            html += '<div class="card-body p-2">';
            html += '<div class="row align-items-center">';
            html += '<div class="col-auto"><span style="font-size: 20px;">' + icon + '</span></div>';
            html += '<div class="col-md-3">';
            html += '<input type="text" class="form-control form-control-sm" placeholder="Feldname (z.B. HOLDER_NAME)" value="' + (field.name || '') + '" data-index="' + index + '" data-prop="name">';
            html += '<small class="text-muted">Platzhalter: {' + (field.name || '...') + '}</small>';
            html += '</div>';
            html += '<div class="col-md-3">';
            html += '<input type="text" class="form-control form-control-sm" placeholder="Label (z.B. Inhabername)" value="' + (field.label || '') + '" data-index="' + index + '" data-prop="label">';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<select class="form-control form-control-sm" data-index="' + index + '" data-prop="type" disabled>';
            html += '<option value="text"' + (field.type === 'text' ? ' selected' : '') + '>Text</option>';
            html += '<option value="date"' + (field.type === 'date' ? ' selected' : '') + '>Datum</option>';
            html += '<option value="number"' + (field.type === 'number' ? ' selected' : '') + '>Nummer</option>';
            html += '<option value="select"' + (field.type === 'select' ? ' selected' : '') + '>Auswahl</option>';
            html += '</select>';
            html += '</div>';
            html += '<div class="col-md-2">';
            html += '<div class="custom-control custom-checkbox">';
            html += '<input type="checkbox" class="custom-control-input" id="req_' + index + '" data-index="' + index + '" data-prop="required"' + (field.required ? ' checked' : '') + '>';
            html += '<label class="custom-control-label" for="req_' + index + '">Pflicht</label>';
            html += '</div>';
            html += '</div>';
            html += '<div class="col-auto">';
            html += '<button type="button" class="btn btn-sm btn-outline-danger remove-field" data-index="' + index + '" title="Feld löschen"><i data-feather="x"></i></button>';
            html += '</div>';
            html += '</div>';
            
            if (field.type === 'select') {
                html += '<div class="row mt-2">';
                html += '<div class="col-md-12">';
                html += '<input type="text" class="form-control form-control-sm" placeholder="Optionen (kommagetrennt, z.B.: Option1, Option2, Option3)" value="' + (field.options ? field.options.join(', ') : '') + '" data-index="' + index + '" data-prop="options">';
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
        
        // Platzhalter aktualisieren wenn Name sich ändert
        if (prop === 'name' || prop === 'label') {
            updateCustomFieldPlaceholders();
        }
    });
    
    // Modal zurücksetzen beim Öffnen
    $('#categoryModal').on('show.bs.modal', function(e) {
        // Nur zurücksetzen wenn es nicht vom Edit-Button kommt
        if (!$(e.relatedTarget).hasClass('edit-category')) {
            $('#categoryForm')[0].reset();
            $('#categoryAction').val('create');
            $('#categoryId').val('');
            $('#categoryModalTitle').text('Kategorie erstellen');
            fields = [];
            renderFields();
            updateCustomFieldPlaceholders();
            
            // Schema Builder zurücksetzen
            schemaElements = [];
            updateSchemaPreview();
        }
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
        
        // Schema in Drag & Drop Builder laden
        schemaElements = parseSchemaToElements(category.number_schema);
        updateSchemaPreview();
        
        fields = category.fields || [];
        renderFields();
        updateCustomFieldPlaceholders();
        
        $('#categoryModal').modal('show');
    });
    
    // Schema aus String parsen (für Bearbeitung)
    function parseSchemaToElements(schema) {
        const elements = [];
        const regex = /(\{[^}]+\}|[^{]+)/g;
        const matches = schema.match(regex);
        
        if (matches) {
            matches.forEach(match => {
                elements.push(match);
            });
        }
        
        return elements;
    }
    
    // Formular absenden
    $('#categoryForm').submit(function(e) {
        e.preventDefault();
        
        $('#fieldsJson').val(JSON.stringify(fields));
        
        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i data-feather="loader"></i> Speichert...');
        
        $.post('', $(this).serialize(), function(response) {
            $btn.prop('disabled', false).html('<i data-feather="save"></i> Speichern');
            feather.replace();
            
            if (response.success) {
                $('#categoryModal').modal('hide');
                alert(response.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        }, 'json').fail(function(xhr) {
            $btn.prop('disabled', false).html('<i data-feather="save"></i> Speichern');
            feather.replace();
            alert('Fehler beim Speichern: ' + (xhr.responseText || 'Unbekannter Fehler'));
        });
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
