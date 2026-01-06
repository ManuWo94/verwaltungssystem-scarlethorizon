<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Berechtigung prüfen
checkLogin();
if (!currentUserCan('licenses', 'view')) {
    header('Location: ' . getBasePath() . 'access_denied.php');
    exit;
}

// Daten laden
$licenses = loadJsonData('licenses.json');
$categories = loadJsonData('license_categories.json');
$allUsers = loadJsonData('users.json');

// Nur aktive Kategorien anzeigen
$activeCategories = array_filter($categories, function($cat) {
    return $cat['active'] ?? true;
});

// AJAX Handler - VOR dem Header!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Error-Reporting für Debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Nicht direkt ausgeben
    
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // Debug-Log
    error_log("License AJAX Request - Action: " . $action);
    
    // Lizenznummer generieren
    if ($action === 'generate_number') {
        $categoryId = $_POST['category_id'] ?? '';
        $category = null;
        
        foreach ($categories as $cat) {
            if ($cat['id'] === $categoryId) {
                $category = $cat;
                break;
            }
        }
        
        if (!$category) {
            echo json_encode(['success' => false, 'message' => 'Kategorie nicht gefunden']);
            exit;
        }
        
        $licenseNumber = generateLicenseNumber($category, $licenses);
        echo json_encode(['success' => true, 'number' => $licenseNumber]);
        exit;
    }
    
    // Lizenz erstellen
    if ($action === 'create') {
        try {
            checkModulePermission('licenses', 'create');
            
            $categoryId = $_POST['category_id'] ?? '';
            $category = null;
            
            foreach ($categories as $cat) {
                if ($cat['id'] === $categoryId) {
                    $category = $cat;
                    break;
                }
            }
            
            if (!$category) {
                echo json_encode(['success' => false, 'message' => 'Kategorie nicht gefunden']);
                exit;
            }
            
            $licenseNumber = generateLicenseNumber($category, $licenses);
            $startDate = $_POST['start_date'] ?? date('Y-m-d');
            $durationDays = intval($_POST['duration_days'] ?? $category['default_duration_days']);
            $endDate = date('Y-m-d', strtotime($startDate . ' + ' . $durationDays . ' days'));
            $notificationEnabled = isset($_POST['notification_enabled']) && $_POST['notification_enabled'] === 'true';
            $notificationDays = intval($_POST['notification_days'] ?? $category['notification_days_before']);
            
            // Felder sammeln
            $fields = [];
            if (isset($category['fields']) && is_array($category['fields'])) {
                foreach ($category['fields'] as $field) {
                    $fieldName = $field['name'];
                    $fields[$fieldName] = $_POST['field_' . $fieldName] ?? '';
                }
            }
            
            // Lizenztext generieren
            $licenseText = generateLicenseText($category, $licenseNumber, $fields, $startDate, $endDate);
            
            // Neue Lizenz erstellen
            $newLicense = [
                'id' => uniqid('lic_', true),
                'category_id' => $categoryId,
                'category_name' => $category['name'],
                'license_number' => $licenseNumber,
                'tg_number' => $_POST['tg_number'] ?? '',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'duration_days' => $durationDays,
                'status' => 'active',
                'notification_enabled' => $notificationEnabled,
                'notification_days_before' => $notificationDays,
                'notification_sent' => false,
                'fields' => $fields,
                'license_text' => $licenseText,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user_id'],
                'created_by_name' => $_SESSION['username'],
                'renewed_from' => $_POST['renewed_from'] ?? null
            ];
            
            $licenses[] = $newLicense;
            
            if (saveJsonData('licenses.json', $licenses)) {
                echo json_encode(['success' => true, 'message' => 'Lizenz erfolgreich erstellt', 'license' => $newLicense]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Datei']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Lizenz löschen
    if ($action === 'delete') {
        checkModulePermission('licenses', 'delete');
        
        $licenseId = $_POST['license_id'] ?? '';
        $licenses = array_filter($licenses, function($lic) use ($licenseId) {
            return $lic['id'] !== $licenseId;
        });
        
        if (saveJsonData('licenses.json', array_values($licenses))) {
            echo json_encode(['success' => true, 'message' => 'Lizenz gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
        }
        exit;
    }
    
    // Wenn keine Action erkannt wurde
    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion: ' . $action]);
    exit;
}

// Header nur bei GET-Anfragen laden
require_once __DIR__ . '/../includes/header.php';
$basePath = getBasePath();

// Hilfsfunktion: Lizenznummer generieren
function generateLicenseNumber($category, $licenses) {
    $schema = $category['number_schema'];
    $year = date('Y');
    
    // Zähler für diese Kategorie ermitteln
    $categoryLicenses = array_filter($licenses, function($lic) use ($category) {
        return $lic['category_id'] === $category['id'];
    });
    
    $counter = count($categoryLicenses) + 1;
    
    // Platzhalter ersetzen
    $number = str_replace('{YEAR}', $year, $schema);
    
    // {NUM:X} - Nummer mit X Stellen
    if (preg_match('/{NUM:(\d+)}/', $number, $matches)) {
        $digits = intval($matches[1]);
        $formatted = str_pad($counter, $digits, '0', STR_PAD_LEFT);
        $number = preg_replace('/{NUM:\d+}/', $formatted, $number);
    }
    
    return $number;
}

// Hilfsfunktion: Lizenztext generieren
function generateLicenseText($category, $licenseNumber, $fields, $startDate, $endDate) {
    global $allUsers;
    
    $template = $category['template'];
    
    // Systemfelder
    $replacements = [
        '{LICENSE_NUMBER}' => $licenseNumber,
        '{START_DATE}' => date('d.m.Y', strtotime($startDate)),
        '{END_DATE}' => date('d.m.Y', strtotime($endDate)),
        '{ISSUE_DATE}' => date('d.m.Y'),
        '{ISSUER_NAME}' => $_SESSION['username'],
        '{ISSUER_ROLE}' => getCleanRoleName($_SESSION['role'] ?? 'User')
    ];
    
    // Benutzerfelder
    foreach ($fields as $key => $value) {
        $replacements['{' . $key . '}'] = $value;
    }
    
    // Ersetzen
    $text = str_replace(array_keys($replacements), array_values($replacements), $template);
    
    return $text;
}

// Hilfsfunktion: Rolle ohne Klammern
function getCleanRoleName($role) {
    // Entferne Text in Klammern
    return preg_replace('/\s*\([^)]*\)/', '', $role);
}

// Aktive Lizenzen filtern
$activeLicenses = array_filter($licenses, function($lic) {
    return $lic['status'] === 'active';
});

// Nach Ablaufdatum sortieren
usort($activeLicenses, function($a, $b) {
    return strtotime($a['end_date']) - strtotime($b['end_date']);
});

?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i data-feather="file-text"></i> Lizenzverwaltung
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo $basePath; ?>modules/license_archive.php" class="btn btn-sm btn-outline-secondary mr-2">
                        <i data-feather="archive"></i> Archiv
                    </a>
                    <?php if (currentUserCan('licenses', 'create')): ?>
                    <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#createLicenseModal">
                        <i data-feather="plus"></i> Neue Lizenz
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?php echo count($activeLicenses); ?></h3>
                            <p class="text-muted mb-0">Aktive Lizenzen</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?php echo count($activeCategories); ?></h3>
                            <p class="text-muted mb-0">Kategorien</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <?php
                            $expiringSoon = array_filter($activeLicenses, function($lic) {
                                $daysUntilExpiry = (strtotime($lic['end_date']) - time()) / 86400;
                                return $daysUntilExpiry <= 14 && $daysUntilExpiry >= 0;
                            });
                            ?>
                            <h3 class="mb-0 text-warning"><?php echo count($expiringSoon); ?></h3>
                            <p class="text-muted mb-0">Laufen bald ab</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <?php
                            $expired = array_filter($licenses, function($lic) {
                                return $lic['status'] === 'archived';
                            });
                            ?>
                            <h3 class="mb-0"><?php echo count($expired); ?></h3>
                            <p class="text-muted mb-0">Archiviert</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form class="form-inline" id="filterForm">
                        <div class="form-group mr-3">
                            <label for="filterCategory" class="mr-2">Kategorie:</label>
                            <select class="form-control" id="filterCategory">
                                <option value="">Alle</option>
                                <?php foreach ($activeCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mr-3">
                            <label for="filterSearch" class="mr-2">Suche:</label>
                            <input type="text" class="form-control" id="filterSearch" placeholder="Nummer oder Inhaber">
                        </div>
                        <button type="button" class="btn btn-secondary" id="resetFilter">Zurücksetzen</button>
                    </form>
                </div>
            </div>

            <!-- Lizenzliste -->
            <div class="card">
                <div class="card-header">
                    <strong>Aktive Lizenzen</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="licensesTable">
                            <thead>
                                <tr>
                                    <th>Lizenznummer</th>
                                    <th>Kategorie</th>
                                    <th>Inhaber</th>
                                    <th>Gültig bis</th>
                                    <th>Status</th>
                                    <th>Erstellt von</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activeLicenses)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        Keine aktiven Lizenzen vorhanden
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($activeLicenses as $license): ?>
                                <?php
                                $daysUntilExpiry = (strtotime($license['end_date']) - time()) / 86400;
                                $statusClass = '';
                                $statusText = 'Aktiv';
                                
                                if ($daysUntilExpiry < 0) {
                                    $statusClass = 'badge-danger';
                                    $statusText = 'Abgelaufen';
                                } elseif ($daysUntilExpiry <= 7) {
                                    $statusClass = 'badge-danger';
                                    $statusText = ceil($daysUntilExpiry) . ' Tage';
                                } elseif ($daysUntilExpiry <= 14) {
                                    $statusClass = 'badge-warning';
                                    $statusText = ceil($daysUntilExpiry) . ' Tage';
                                } else {
                                    $statusClass = 'badge-success';
                                    $statusText = 'Aktiv';
                                }
                                
                                $holderName = $license['fields']['HOLDER_NAME'] ?? 'N/A';
                                ?>
                                <tr data-license-id="<?php echo htmlspecialchars($license['id']); ?>" 
                                    data-category-id="<?php echo htmlspecialchars($license['category_id']); ?>"
                                    data-holder="<?php echo htmlspecialchars($holderName); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($license['license_number']); ?></strong>
                                        <?php if ($license['renewed_from']): ?>
                                        <span class="badge badge-info badge-sm ml-1">Erneuert</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($license['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($holderName); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($license['end_date'])); ?></td>
                                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                    <td><?php echo htmlspecialchars($license['created_by_name']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-license" 
                                                data-license='<?php echo json_encode($license, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                            <i data-feather="eye"></i>
                                        </button>
                                        <?php if (currentUserCan('licenses', 'create')): ?>
                                        <button class="btn btn-sm btn-success renew-license" 
                                                data-license='<?php echo json_encode($license, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                            <i data-feather="refresh-cw"></i>
                                        </button>
                                        <?php endif; ?>
                                        <?php if (currentUserCan('licenses', 'delete')): ?>
                                        <button class="btn btn-sm btn-danger delete-license" 
                                                data-license-id="<?php echo htmlspecialchars($license['id']); ?>"
                                                data-license-number="<?php echo htmlspecialchars($license['license_number']); ?>">
                                            <i data-feather="trash-2"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal: Neue Lizenz erstellen -->
<div class="modal fade" id="createLicenseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Neue Lizenz erstellen</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="createLicenseForm">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="renewed_from" id="renewedFrom">
                    
                    <!-- Schritt 1: Kategorie wählen -->
                    <div id="step1">
                        <div class="form-group">
                            <label for="categorySelect">Lizenzkategorie *</label>
                            <select class="form-control" id="categorySelect" name="category_id" required>
                                <option value="">-- Bitte wählen --</option>
                                <?php foreach ($activeCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['id']); ?>" 
                                        data-category='<?php echo json_encode($cat, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary" id="nextToStep2">Weiter</button>
                    </div>
                    
                    <!-- Schritt 2: Daten eingeben -->
                    <div id="step2" style="display: none;">
                        <h6 class="mb-3">Lizenzdaten</h6>
                        
                        <!-- Lizenznummer Vorschau -->
                        <div class="alert alert-info">
                            <strong>Lizenznummer:</strong> <span id="licenseNumberPreview">-</span>
                        </div>
                        
                        <!-- Dynamische Felder -->
                        <div id="dynamicFields"></div>
                        
                        <!-- TG-Nummer -->
                        <div class="form-group">
                            <label for="tgNumber">TG-Nummer</label>
                            <input type="text" class="form-control" id="tgNumber" name="tg_number" placeholder="z.B. TG-2026-001">
                            <small class="form-text text-muted">Optional: Interne Tracking/Geschäftsnummer</small>
                        </div>
                        
                        <!-- Laufzeit -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="startDate">Gültig ab</label>
                                    <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="durationDays">Laufzeit (Tage)</label>
                                    <input type="number" class="form-control" id="durationDays" name="duration_days" min="1" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Benachrichtigung -->
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="notificationEnabled" name="notification_enabled" value="true">
                                <label class="custom-control-label" for="notificationEnabled">
                                    Ablaufbenachrichtigung aktivieren
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group" id="notificationDaysGroup" style="display: none;">
                            <label for="notificationDays">Benachrichtigung senden (Tage vor Ablauf)</label>
                            <input type="number" class="form-control" id="notificationDays" name="notification_days" min="1" max="365">
                        </div>
                        
                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" id="backToStep1">Zurück</button>
                            <button type="submit" class="btn btn-success">
                                <i data-feather="check"></i> Lizenz erstellen
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Lizenz anzeigen -->
<div class="modal fade" id="viewLicenseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lizenz Details</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <button class="btn btn-sm btn-primary" id="copyLicenseText">
                        <i data-feather="copy"></i> Text kopieren
                    </button>
                </div>
                <pre id="licenseTextDisplay" style="white-space: pre-wrap; background: #f8f9fa; padding: 20px; border-radius: 5px; font-family: 'Courier New', monospace;"></pre>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    feather.replace();
    
    let currentCategory = null;
    
    // Kategorie gewählt
    $('#categorySelect').change(function() {
        const categoryData = $(this).find(':selected').data('category');
        currentCategory = categoryData;
    });
    
    // Weiter zu Schritt 2
    $('#nextToStep2').click(function() {
        if (!$('#categorySelect').val()) {
            alert('Bitte wählen Sie eine Kategorie');
            return;
        }
        
        // Lizenznummer generieren
        $.post('', {
            action: 'generate_number',
            category_id: $('#categorySelect').val()
        }, function(response) {
            if (response.success) {
                $('#licenseNumberPreview').text(response.number);
            }
        }, 'json');
        
        // Dynamische Felder erstellen
        let fieldsHtml = '';
        currentCategory.fields.forEach(function(field) {
            fieldsHtml += '<div class="form-group">';
            fieldsHtml += '<label for="field_' + field.name + '">' + field.label;
            if (field.required) fieldsHtml += ' *';
            fieldsHtml += '</label>';
            
            if (field.type === 'select') {
                fieldsHtml += '<select class="form-control" name="field_' + field.name + '" id="field_' + field.name + '"';
                if (field.required) fieldsHtml += ' required';
                fieldsHtml += '>';
                fieldsHtml += '<option value="">-- Bitte wählen --</option>';
                field.options.forEach(function(opt) {
                    fieldsHtml += '<option value="' + opt + '">' + opt + '</option>';
                });
                fieldsHtml += '</select>';
            } else {
                fieldsHtml += '<input type="' + field.type + '" class="form-control" name="field_' + field.name + '" id="field_' + field.name + '"';
                if (field.required) fieldsHtml += ' required';
                fieldsHtml += '>';
            }
            
            fieldsHtml += '</div>';
        });
        
        $('#dynamicFields').html(fieldsHtml);
        $('#durationDays').val(currentCategory.default_duration_days);
        $('#notificationDays').val(currentCategory.notification_days_before);
        
        if (currentCategory.notification_enabled) {
            $('#notificationEnabled').prop('checked', true);
            $('#notificationDaysGroup').show();
        }
        
        $('#step1').hide();
        $('#step2').show();
    });
    
    // Zurück zu Schritt 1
    $('#backToStep1').click(function() {
        $('#step2').hide();
        $('#step1').show();
    });
    
    // Benachrichtigung Toggle
    $('#notificationEnabled').change(function() {
        if ($(this).is(':checked')) {
            $('#notificationDaysGroup').show();
        } else {
            $('#notificationDaysGroup').hide();
        }
    });
    
    // Formular absenden
    $('#createLicenseForm').submit(function(e) {
        e.preventDefault();
        
        const $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i data-feather="loader"></i> Speichert...');
        
        $.ajax({
            url: '',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html('<i data-feather="check"></i> Lizenz erstellen');
                feather.replace();
                
                if (response.success) {
                    $('#createLicenseModal').modal('hide');
                    alert('Lizenz erfolgreich erstellt!');
                    location.reload();
                } else {
                    alert('Fehler: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('<i data-feather="check"></i> Lizenz erstellen');
                feather.replace();
                
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                
                let errorMsg = 'Unbekannter Fehler';
                if (xhr.responseText) {
                    try {
                        const errorData = JSON.parse(xhr.responseText);
                        errorMsg = errorData.message || errorMsg;
                    } catch(e) {
                        // Wenn es kein JSON ist, zeige die ersten 200 Zeichen
                        errorMsg = xhr.responseText.substring(0, 200);
                    }
                }
                alert('Fehler beim Speichern: ' + errorMsg + '\n\nStatus: ' + xhr.status + '\nBitte Konsole für Details prüfen.');
            }
        });
    });
    
    // Lizenz anzeigen
    $(document).on('click', '.view-license', function() {
        const license = $(this).data('license');
        $('#licenseTextDisplay').text(license.license_text);
        $('#viewLicenseModal').modal('show');
        
        // Text kopieren
        $('#copyLicenseText').off('click').on('click', function() {
            const text = $('#licenseTextDisplay').text();
            navigator.clipboard.writeText(text).then(function() {
                alert('Text in Zwischenablage kopiert!');
            });
        });
    });
    
    // Lizenz erneuern
    $(document).on('click', '.renew-license', function() {
        const license = $(this).data('license');
        
        if (!confirm('Lizenz ' + license.license_number + ' erneuern?')) return;
        
        // Modal öffnen mit vorbefüllten Daten
        $('#categorySelect').val(license.category_id).change();
        $('#renewedFrom').val(license.id);
        $('#createLicenseModal').modal('show');
        
        // Kurz warten und dann zu Schritt 2
        setTimeout(function() {
            $('#nextToStep2').click();
            
            // Felder befüllen
            setTimeout(function() {
                Object.keys(license.fields).forEach(function(key) {
                    $('#field_' + key).val(license.fields[key]);
                });
            }, 100);
        }, 100);
    });
    
    // Lizenz löschen
    $(document).on('click', '.delete-license', function() {
        const licenseId = $(this).data('license-id');
        const licenseNumber = $(this).data('license-number');
        
        if (!confirm('Lizenz ' + licenseNumber + ' wirklich löschen?')) return;
        
        $.post('', {
            action: 'delete',
            license_id: licenseId
        }, function(response) {
            if (response.success) {
                alert('Lizenz gelöscht');
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        }, 'json');
    });
    
    // Filter
    function applyFilter() {
        const category = $('#filterCategory').val();
        const search = $('#filterSearch').val().toLowerCase();
        
        $('#licensesTable tbody tr').each(function() {
            const $row = $(this);
            const rowCategory = $row.data('category-id');
            const rowHolder = ($row.data('holder') || '').toLowerCase();
            const rowNumber = $row.find('td:first strong').text().toLowerCase();
            
            let show = true;
            
            if (category && rowCategory !== category) show = false;
            if (search && !rowHolder.includes(search) && !rowNumber.includes(search)) show = false;
            
            $row.toggle(show);
        });
    }
    
    $('#filterCategory, #filterSearch').on('change keyup', applyFilter);
    $('#resetFilter').click(function() {
        $('#filterCategory').val('');
        $('#filterSearch').val('');
        applyFilter();
    });
    
    // Modal geschlossen - Form zurücksetzen
    $('#createLicenseModal').on('hidden.bs.modal', function() {
        $('#createLicenseForm')[0].reset();
        $('#step2').hide();
        $('#step1').show();
        $('#renewedFrom').val('');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
