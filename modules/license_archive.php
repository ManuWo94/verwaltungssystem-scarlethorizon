<?php
require_once __DIR__ . '/../includes/session_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

checkLogin();
if (!currentUserCan('licenses', 'view')) {
    header('Location: ' . getBasePath() . 'access_denied.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';
$basePath = getBasePath();
$licenses = loadJsonData('licenses.json');

// AJAX Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
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
    
    // Mehrere Lizenzen löschen
    if ($action === 'delete_multiple') {
        checkModulePermission('licenses', 'delete');
        
        $licenseIds = json_decode($_POST['license_ids'] ?? '[]', true);
        $licenses = array_filter($licenses, function($lic) use ($licenseIds) {
            return !in_array($lic['id'], $licenseIds);
        });
        
        if (saveJsonData('licenses.json', array_values($licenses))) {
            echo json_encode(['success' => true, 'message' => count($licenseIds) . ' Lizenzen gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
        }
        exit;
    }
    
    // Lizenzen nach Alter löschen
    if ($action === 'delete_by_age') {
        checkModulePermission('licenses', 'delete');
        
        $days = intval($_POST['days'] ?? 0);
        $cutoffDate = date('Y-m-d', strtotime('-' . $days . ' days'));
        
        $beforeCount = count($licenses);
        $licenses = array_filter($licenses, function($lic) use ($cutoffDate) {
            if ($lic['status'] !== 'archived') return true;
            return $lic['end_date'] >= $cutoffDate;
        });
        
        $deletedCount = $beforeCount - count($licenses);
        
        if (saveJsonData('licenses.json', array_values($licenses))) {
            echo json_encode(['success' => true, 'message' => $deletedCount . ' Lizenzen gelöscht']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen']);
        }
        exit;
    }
    
    // Lizenz reaktivieren
    if ($action === 'restore') {
        checkModulePermission('licenses', 'create');
        
        $licenseId = $_POST['license_id'] ?? '';
        
        foreach ($licenses as &$lic) {
            if ($lic['id'] === $licenseId) {
                $lic['status'] = 'active';
                $lic['end_date'] = date('Y-m-d', strtotime('+' . $lic['duration_days'] . ' days'));
                break;
            }
        }
        
        if (saveJsonData('licenses.json', $licenses)) {
            echo json_encode(['success' => true, 'message' => 'Lizenz reaktiviert']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler beim Reaktivieren']);
        }
        exit;
    }
}

// Archivierte Lizenzen filtern
$archivedLicenses = array_filter($licenses, function($lic) {
    return $lic['status'] === 'archived';
});

// Nach Enddatum sortieren (neueste zuerst)
usort($archivedLicenses, function($a, $b) {
    return strtotime($b['end_date']) - strtotime($a['end_date']);
});

?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i data-feather="archive"></i> Lizenzarchiv
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo $basePath; ?>modules/licenses.php" class="btn btn-sm btn-outline-secondary mr-2">
                        <i data-feather="arrow-left"></i> Zurück zu Lizenzen
                    </a>
                    <?php if (currentUserCan('licenses', 'delete')): ?>
                    <button class="btn btn-sm btn-danger" data-toggle="modal" data-target="#bulkDeleteModal">
                        <i data-feather="trash-2"></i> Sammellöschung
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="mb-0"><?php echo count($archivedLicenses); ?></h3>
                            <p class="text-muted mb-0">Archivierte Lizenzen</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <?php
                            $oldLicenses = array_filter($archivedLicenses, function($lic) {
                                $daysOld = (time() - strtotime($lic['end_date'])) / 86400;
                                return $daysOld > 90;
                            });
                            ?>
                            <h3 class="mb-0"><?php echo count($oldLicenses); ?></h3>
                            <p class="text-muted mb-0">Älter als 90 Tage</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <?php
                            $selectedIds = 0;
                            ?>
                            <h3 class="mb-0" id="selectedCount">0</h3>
                            <p class="text-muted mb-0">Ausgewählt</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Massenaktionen -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <form class="form-inline">
                                <label for="filterSearch" class="mr-2">Suche:</label>
                                <input type="text" class="form-control mr-3" id="filterSearch" placeholder="Nummer oder Inhaber">
                                <button type="button" class="btn btn-secondary" id="resetFilter">Zurücksetzen</button>
                            </form>
                        </div>
                        <?php if (currentUserCan('licenses', 'delete')): ?>
                        <div class="col-md-6 text-right">
                            <button class="btn btn-warning" id="selectAll">
                                <i data-feather="check-square"></i> Alle auswählen
                            </button>
                            <button class="btn btn-danger" id="deleteSelected" disabled>
                                <i data-feather="trash-2"></i> Ausgewählte löschen
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Archiv Liste -->
            <div class="card">
                <div class="card-header">
                    <strong>Archivierte Lizenzen</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="archiveTable">
                            <thead>
                                <tr>
                                    <?php if (currentUserCan('licenses', 'delete')): ?>
                                    <th width="40">
                                        <input type="checkbox" id="selectAllCheckbox">
                                    </th>
                                    <?php endif; ?>
                                    <th>Lizenznummer</th>
                                    <th>Kategorie</th>
                                    <th>Inhaber</th>
                                    <th>Abgelaufen am</th>
                                    <th>Tage abgelaufen</th>
                                    <th>Erstellt von</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($archivedLicenses)): ?>
                                <tr>
                                    <td colspan="<?php echo currentUserCan('licenses', 'delete') ? '8' : '7'; ?>" class="text-center text-muted py-4">
                                        Keine archivierten Lizenzen vorhanden
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($archivedLicenses as $license): ?>
                                <?php
                                $daysExpired = ceil((time() - strtotime($license['end_date'])) / 86400);
                                $holderName = $license['fields']['HOLDER_NAME'] ?? 'N/A';
                                ?>
                                <tr data-license-id="<?php echo htmlspecialchars($license['id']); ?>"
                                    data-holder="<?php echo htmlspecialchars($holderName); ?>">
                                    <?php if (currentUserCan('licenses', 'delete')): ?>
                                    <td>
                                        <input type="checkbox" class="license-checkbox" 
                                               value="<?php echo htmlspecialchars($license['id']); ?>">
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <strong><?php echo htmlspecialchars($license['license_number']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($license['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($holderName); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($license['end_date'])); ?></td>
                                    <td>
                                        <?php if ($daysExpired > 180): ?>
                                        <span class="badge badge-danger"><?php echo $daysExpired; ?> Tage</span>
                                        <?php elseif ($daysExpired > 90): ?>
                                        <span class="badge badge-warning"><?php echo $daysExpired; ?> Tage</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary"><?php echo $daysExpired; ?> Tage</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($license['created_by_name']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-license" 
                                                data-license='<?php echo json_encode($license, JSON_HEX_APOS | JSON_HEX_QUOT); ?>'>
                                            <i data-feather="eye"></i>
                                        </button>
                                        <?php if (currentUserCan('licenses', 'create')): ?>
                                        <button class="btn btn-sm btn-success restore-license" 
                                                data-license-id="<?php echo htmlspecialchars($license['id']); ?>"
                                                data-license-number="<?php echo htmlspecialchars($license['license_number']); ?>">
                                            <i data-feather="rotate-ccw"></i>
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

<!-- Modal: Sammellöschung -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Sammellöschung</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong>Warnung:</strong> Diese Aktion kann nicht rückgängig gemacht werden!</p>
                
                <div class="form-group">
                    <label>Alle archivierten Lizenzen löschen, die älter sind als:</label>
                    <select class="form-control" id="deleteAgeDays">
                        <option value="30">30 Tage</option>
                        <option value="60">60 Tage</option>
                        <option value="90" selected>90 Tage</option>
                        <option value="180">180 Tage</option>
                        <option value="365">1 Jahr</option>
                    </select>
                </div>
                
                <div class="alert alert-warning">
                    <strong>Hinweis:</strong> Es werden nur archivierte Lizenzen gelöscht, deren Ablaufdatum mehr als die angegebenen Tage zurückliegt.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-danger" id="confirmBulkDelete">
                    <i data-feather="trash-2"></i> Jetzt löschen
                </button>
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
    
    // Auswahlzähler aktualisieren
    function updateSelectedCount() {
        const count = $('.license-checkbox:checked').length;
        $('#selectedCount').text(count);
        $('#deleteSelected').prop('disabled', count === 0);
    }
    
    // Alle auswählen
    $('#selectAllCheckbox, #selectAll').click(function() {
        const checked = $('#selectAllCheckbox').is(':checked') || $(this).is('#selectAll');
        $('.license-checkbox:visible').prop('checked', checked);
        if ($(this).is('#selectAll')) {
            $('#selectAllCheckbox').prop('checked', true);
        }
        updateSelectedCount();
    });
    
    // Einzelne Checkbox
    $(document).on('change', '.license-checkbox', function() {
        updateSelectedCount();
        
        const allChecked = $('.license-checkbox:visible').length === $('.license-checkbox:visible:checked').length;
        $('#selectAllCheckbox').prop('checked', allChecked);
    });
    
    // Ausgewählte löschen
    $('#deleteSelected').click(function() {
        const selected = $('.license-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (!confirm('Wirklich ' + selected.length + ' Lizenzen permanent löschen?')) return;
        
        $.post('', {
            action: 'delete_multiple',
            license_ids: JSON.stringify(selected)
        }, function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        }, 'json');
    });
    
    // Sammellöschung
    $('#confirmBulkDelete').click(function() {
        const days = $('#deleteAgeDays').val();
        
        if (!confirm('Wirklich alle archivierten Lizenzen löschen, die älter als ' + days + ' Tage sind?')) return;
        
        $.post('', {
            action: 'delete_by_age',
            days: days
        }, function(response) {
            if (response.success) {
                alert(response.message);
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        }, 'json');
    });
    
    // Einzelne Lizenz löschen
    $(document).on('click', '.delete-license', function() {
        const licenseId = $(this).data('license-id');
        const licenseNumber = $(this).data('license-number');
        
        if (!confirm('Lizenz ' + licenseNumber + ' permanent löschen?')) return;
        
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
    
    // Lizenz reaktivieren
    $(document).on('click', '.restore-license', function() {
        const licenseId = $(this).data('license-id');
        const licenseNumber = $(this).data('license-number');
        
        if (!confirm('Lizenz ' + licenseNumber + ' reaktivieren?')) return;
        
        $.post('', {
            action: 'restore',
            license_id: licenseId
        }, function(response) {
            if (response.success) {
                alert('Lizenz reaktiviert');
                location.reload();
            } else {
                alert('Fehler: ' + response.message);
            }
        }, 'json');
    });
    
    // Lizenz anzeigen
    $(document).on('click', '.view-license', function() {
        const license = $(this).data('license');
        $('#licenseTextDisplay').text(license.license_text);
        $('#viewLicenseModal').modal('show');
        
        $('#copyLicenseText').off('click').on('click', function() {
            const text = $('#licenseTextDisplay').text();
            navigator.clipboard.writeText(text).then(function() {
                alert('Text in Zwischenablage kopiert!');
            });
        });
    });
    
    // Filter
    function applyFilter() {
        const search = $('#filterSearch').val().toLowerCase();
        
        $('#archiveTable tbody tr').each(function() {
            const $row = $(this);
            const rowHolder = ($row.data('holder') || '').toLowerCase();
            const rowNumber = $row.find('td:nth-child(2) strong').text().toLowerCase();
            
            let show = true;
            if (search && !rowHolder.includes(search) && !rowNumber.includes(search)) show = false;
            
            $row.toggle(show);
        });
        
        updateSelectedCount();
    }
    
    $('#filterSearch').on('keyup', applyFilter);
    $('#resetFilter').click(function() {
        $('#filterSearch').val('');
        applyFilter();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
