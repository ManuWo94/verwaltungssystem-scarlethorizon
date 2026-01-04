<?php
/**
 * Beschlagnahmeprotokoll Modul
 * Für die Verwaltung von Waffen- und Beweismittelverwahrung
 */

// Session starten und Authentifizierung prüfen
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../evidence_check.php'; // Spezielle Zugriffssteuerung für Beschlagnahmungen
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

$pageTitle = 'Beschlagnahmeprotokoll';

// Aktionen verarbeiten
$successMessage = '';
$errorMessage = '';

// Protokolle laden
$protocols = getJsonData('confiscation_protocols.json');

// Aktion: Neues Protokoll speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Neues Protokoll erstellen
    if ($_POST['action'] === 'create_protocol') {
        $protocolType = sanitize($_POST['protocol_type']);
        $ownerId = sanitize($_POST['owner_id']);
        $ownerTgNumber = sanitize($_POST['owner_tg_number']);
        $ownerName = sanitize($_POST['owner_name']);
        $sheriffId = sanitize($_POST['sheriff_id']);
        $confiscationDate = sanitize($_POST['confiscation_date']);
        $retentionDays = intval(sanitize($_POST['retention_days'] ?? 0));
        $remarks = sanitize($_POST['remarks'] ?? '');
        
        // Berechne das geplante Rückgabedatum
        $plannedReturnDate = calculateReturnDate($retentionDays);
        
        // Waffen/Beweismittel verarbeiten
        $items = [];
        if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
            for ($i = 0; $i < count($_POST['item_name']); $i++) {
                if (!empty($_POST['item_name'][$i])) {
                    $items[] = [
                        'name' => sanitize($_POST['item_name'][$i]),
                        'serial' => sanitize($_POST['item_serial'][$i] ?? '')
                    ];
                }
            }
        }
        
        // Aktueller Benutzer und Zeitstempel
        $currentUser = getCurrentUser();
        $timestamp = date('Y-m-d H:i:s');
        
        // Neues Protokoll
        $newProtocol = [
            'id' => generateUniqueId(),
            'protocol_type' => $protocolType,
            'owner_id' => $ownerId,
            'owner_tg_number' => $ownerTgNumber,
            'owner_name' => $ownerName,
            'sheriff_id' => $sheriffId,
            'sheriff_name' => sanitize($_POST['sheriff_name'] ?? ''),
            'confiscation_date' => $confiscationDate,
            'retention_days' => $retentionDays,
            'planned_return_date' => $plannedReturnDate,
            'items' => $items,
            'remarks' => $remarks,
            'receiving_staff_id' => $currentUser['id'],
            'receiving_staff_name' => $currentUser['username'],
            'receiving_date' => $timestamp,
            'status' => 'active', // active, returned, evidence
            'return_date' => null,
            'returning_staff_id' => null,
            'returning_staff_name' => null,
            'change_log' => [
                [
                    'user_id' => $currentUser['id'],
                    'username' => $currentUser['username'],
                    'action' => 'erstellt',
                    'details' => '',
                    'timestamp' => $timestamp
                ]
            ]
        ];
        
        // Protokoll speichern
        $protocols[] = $newProtocol;
        if (saveJsonData('confiscation_protocols.json', $protocols)) {
            $successMessage = 'Protokoll erfolgreich erstellt.';
            // Neu laden
            $protocols = getJsonData('confiscation_protocols.json');
        } else {
            $errorMessage = 'Fehler beim Speichern des Protokolls.';
        }
    }
    
    // Protokoll bearbeiten
    elseif ($_POST['action'] === 'edit_protocol' && isset($_POST['protocol_id'])) {
        $protocolId = sanitize($_POST['protocol_id']);
        $protocolType = sanitize($_POST['protocol_type']);
        $ownerId = sanitize($_POST['owner_id']);
        $ownerTgNumber = sanitize($_POST['owner_tg_number']);
        $ownerName = sanitize($_POST['owner_name']);
        $sheriffId = sanitize($_POST['sheriff_id']);
        $sheriffName = sanitize($_POST['sheriff_name']);
        $confiscationDate = sanitize($_POST['confiscation_date']);
        $retentionDays = intval(sanitize($_POST['retention_days'] ?? 0));
        $remarks = sanitize($_POST['remarks'] ?? '');
        
        // Berechne das geplante Rückgabedatum
        $plannedReturnDate = calculateReturnDate($retentionDays);
        
        // Waffen/Beweismittel verarbeiten
        $items = [];
        if (isset($_POST['item_name']) && is_array($_POST['item_name'])) {
            for ($i = 0; $i < count($_POST['item_name']); $i++) {
                if (!empty($_POST['item_name'][$i])) {
                    $items[] = [
                        'name' => sanitize($_POST['item_name'][$i]),
                        'serial' => sanitize($_POST['item_serial'][$i] ?? '')
                    ];
                }
            }
        }
        
        // Protokoll suchen und aktualisieren
        foreach ($protocols as $key => $protocol) {
            if ($protocol['id'] === $protocolId) {
                // Änderungen protokollieren
                $changeDetails = "Protokoll bearbeitet";
                $protocols[$key] = logProtocolChange($protocol, 'bearbeitet', $changeDetails);
                
                // Daten aktualisieren ohne Status zu verändern
                $protocols[$key]['protocol_type'] = $protocolType;
                $protocols[$key]['owner_id'] = $ownerId;
                $protocols[$key]['owner_tg_number'] = $ownerTgNumber;
                $protocols[$key]['owner_name'] = $ownerName;
                $protocols[$key]['sheriff_id'] = $sheriffId;
                $protocols[$key]['sheriff_name'] = $sheriffName;
                $protocols[$key]['confiscation_date'] = $confiscationDate;
                $protocols[$key]['retention_days'] = $retentionDays;
                $protocols[$key]['planned_return_date'] = $plannedReturnDate;
                $protocols[$key]['items'] = $items;
                $protocols[$key]['remarks'] = $remarks;
                
                $successMessage = 'Protokoll erfolgreich aktualisiert.';
                break;
            }
        }
        
        // Speichern
        if (!saveJsonData('confiscation_protocols.json', $protocols)) {
            $errorMessage = 'Fehler beim Aktualisieren des Protokolls.';
        }
    }
    
    // Protokoll löschen
    elseif ($_POST['action'] === 'delete_protocol' && isset($_POST['protocol_id'])) {
        $protocolId = sanitize($_POST['protocol_id']);
        
        // Prüfen, ob der Benutzer Admin ist
        if (!isAdminSession()) {
            $errorMessage = 'Nur Administratoren dürfen Protokolle löschen.';
        } else {
            // Protokoll suchen und löschen
            foreach ($protocols as $key => $protocol) {
                if ($protocol['id'] === $protocolId) {
                    unset($protocols[$key]);
                    $protocols = array_values($protocols); // Array neu indizieren
                    
                    if (saveJsonData('confiscation_protocols.json', $protocols)) {
                        $successMessage = 'Protokoll erfolgreich gelöscht.';
                    } else {
                        $errorMessage = 'Fehler beim Löschen des Protokolls.';
                    }
                    break;
                }
            }
        }
    }
    
    // Waffen zurückgeben
    elseif ($_POST['action'] === 'return_weapon' && isset($_POST['protocol_id'])) {
        $protocolId = sanitize($_POST['protocol_id']);
        
        // Protokoll suchen und aktualisieren
        foreach ($protocols as $key => $protocol) {
            if ($protocol['id'] === $protocolId && $protocol['status'] === 'active' && $protocol['protocol_type'] === 'weapon') {
                $currentUser = getCurrentUser();
                $timestamp = date('Y-m-d H:i:s');
                
                // Änderungen protokollieren
                $protocols[$key] = logProtocolChange($protocol, 'zurückgegeben', 'Waffe zurückgegeben durch ' . $currentUser['username']);
                
                // Status und Rückgabeinformationen aktualisieren
                $protocols[$key]['status'] = 'returned';
                $protocols[$key]['return_date'] = $timestamp;
                $protocols[$key]['returning_staff_id'] = $currentUser['id'];
                $protocols[$key]['returning_staff_name'] = $currentUser['username'];
                
                $successMessage = 'Waffe erfolgreich zurückgegeben.';
                break;
            }
        }
        
        // Speichern
        if (!saveJsonData('confiscation_protocols.json', $protocols)) {
            $errorMessage = 'Fehler beim Aktualisieren des Protokolls.';
        }
    }
    elseif ($_POST['action'] === 'return_weapons' && isset($_POST['protocol_id'])) {
        $protocolId = sanitize($_POST['protocol_id']);
        
        // Protokoll suchen und aktualisieren
        foreach ($protocols as $key => $protocol) {
            if ($protocol['id'] === $protocolId && $protocol['status'] === 'active') {
                $currentUser = getCurrentUser();
                $timestamp = date('Y-m-d H:i:s');
                
                // Änderungen protokollieren
                $protocols[$key] = logProtocolChange($protocol, 'zurückgegeben', 'Waffen zurückgegeben');
                
                // Status und Rückgabeinformationen aktualisieren
                $protocols[$key]['status'] = 'returned';
                $protocols[$key]['return_date'] = $timestamp;
                $protocols[$key]['returning_staff_id'] = $currentUser['id'];
                $protocols[$key]['returning_staff_name'] = $currentUser['username'];
                
                $successMessage = 'Waffen erfolgreich zurückgegeben.';
                break;
            }
        }
        
        // Speichern
        if (!saveJsonData('confiscation_protocols.json', $protocols)) {
            $errorMessage = 'Fehler beim Aktualisieren des Protokolls.';
        }
    }
}

// Protokolle nach Datum sortieren (neueste zuerst)
usort($protocols, function($a, $b) {
    return strtotime($b['receiving_date']) - strtotime($a['receiving_date']);
});

// Benutzer für Dropdown laden
$users = getJsonData('users.json');

// Header einbinden
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" style="margin-left: 220px;">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Beschlagnahmeprotokoll</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#newWeaponProtocolModal">
                        <span data-feather="plus"></span>
                        Neues Waffenrückgabeprotokoll
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary ml-2" data-toggle="modal" data-target="#newEvidenceProtocolModal">
                        <span data-feather="plus"></span>
                        Neue Beweismittelsicherung
                    </button>
                </div>
            </div>
            
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <!-- Suchfunktion -->
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Protokolle durchsuchen</h5>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="input-group mb-3">
                                <input type="text" id="searchInput" class="form-control" placeholder="Name oder TG-Nummer suchen...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" id="searchButton">
                                        <span data-feather="search"></span> Suchen
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select id="protocolTypeFilter" class="form-control">
                                <option value="all">Alle Protokolle</option>
                                <option value="weapon">Nur Waffenrückgaben</option>
                                <option value="evidence">Nur Beweismittel</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Waffenrückgaben -->
            <div class="card mb-4" id="weaponProtocolsCard">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Aktive Waffenrückgabeprotokolle</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="weaponProtocolsTable">
                            <thead>
                                <tr>
                                    <th>TG-Nummer</th>
                                    <th>Besitzer</th>
                                    <th>Sheriff</th>
                                    <th>Aufnahmezeitpunkt</th>
                                    <th>Angenommen von</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $activeWeaponProtocols = false;
                                foreach ($protocols as $protocol):
                                    if ($protocol['status'] === 'active' && $protocol['protocol_type'] === 'weapon'):
                                        $activeWeaponProtocols = true;
                                ?>
                                    <tr class="protocol-row" data-owner="<?php echo strtolower(htmlspecialchars($protocol['owner_name'])); ?>" data-tg="<?php echo strtolower(htmlspecialchars($protocol['owner_tg_number'])); ?>">
                                        <td><?php echo htmlspecialchars($protocol['owner_tg_number']); ?></td>
                                        <td><?php echo htmlspecialchars($protocol['owner_name']); ?></td>
                                        <td><?php echo htmlspecialchars($protocol['sheriff_name'] ?? ''); ?></td>
                                        <td><?php echo formatDate($protocol['receiving_date']); ?></td>
                                        <td><?php echo htmlspecialchars($protocol['receiving_staff_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-protocol" data-toggle="modal" data-target="#viewProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                                <span data-feather="eye"></span>
                                            </button>
                                            <?php if ($protocol['protocol_type'] === 'weapon'): ?>
                                                <button class="btn btn-sm btn-success return-weapons" data-toggle="modal" data-target="#returnWeaponsModal" data-id="<?php echo $protocol['id']; ?>">
                                                    <span data-feather="arrow-up-circle"></span>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php 
                                    endif;
                                endforeach;
                                
                                if (!$activeWeaponProtocols):
                                ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Keine aktiven Waffenrückgabeprotokolle vorhanden</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Beweismittel -->
            <div class="card mb-4" id="evidenceProtocolsCard">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Aktive Beweismittelprotokolle</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="evidenceProtocolsTable">
                            <thead>
                                <tr>
                                    <th>TG-Nummer</th>
                                    <th>Besitzer/Beteiligter</th>
                                    <th>Sheriff</th>
                                    <th>Aufnahmezeitpunkt</th>
                                    <th>Angenommen von</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $activeEvidenceProtocols = false;
                                foreach ($protocols as $protocol):
                                    if ($protocol['status'] === 'active' && $protocol['protocol_type'] === 'evidence'):
                                        $activeEvidenceProtocols = true;
                                ?>
                                    <tr class="protocol-row" data-owner="<?php echo strtolower(htmlspecialchars($protocol['owner_name'])); ?>" data-tg="<?php echo strtolower(htmlspecialchars($protocol['owner_tg_number'])); ?>">
                                        <td><?php echo htmlspecialchars($protocol['owner_tg_number']); ?></td>
                                        <td><?php echo htmlspecialchars($protocol['owner_name']); ?></td>
                                        <td><?php echo htmlspecialchars($protocol['sheriff_name'] ?? ''); ?></td>
                                        <td><?php echo formatDate($protocol['receiving_date']); ?></td>
                                        <td><?php echo htmlspecialchars($protocol['receiving_staff_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary view-protocol" data-toggle="modal" data-target="#viewProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                                <span data-feather="eye"></span>
                                            </button>
                                        <button class="btn btn-sm btn-warning edit-protocol" data-toggle="modal" data-target="#editProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                            <span data-feather="edit-2"></span>
                                        </button>
                                        <?php if ($protocol['protocol_type'] === 'weapon'): ?>
                                            <button class="btn btn-sm btn-success return-weapons" data-toggle="modal" data-target="#returnWeaponsModal" data-id="<?php echo $protocol['id']; ?>">
                                                <span data-feather="check"></span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (isAdminSession()): ?>
                                            <button class="btn btn-sm btn-danger delete-protocol" data-toggle="modal" data-target="#deleteProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                                <span data-feather="trash-2"></span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <h4 class="mt-5">Abgeschlossene Protokolle</h4>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Art</th>
                            <th>TG-Nummer</th>
                            <th>Besitzer</th>
                            <th>Sheriff</th>
                            <th>Aufnahmezeitpunkt</th>
                            <th>Rückgabezeitpunkt</th>
                            <th>Status</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($protocols as $protocol): ?>
                            <?php if ($protocol['status'] === 'returned'): ?>
                                <tr>
                                    <td><?php echo $protocol['protocol_type'] === 'weapon' ? 'Waffenrückgabe' : 'Beweismittel'; ?></td>
                                    <td><?php echo htmlspecialchars($protocol['owner_tg_number']); ?></td>
                                    <td><?php echo htmlspecialchars($protocol['owner_name']); ?></td>
                                    <td><?php echo htmlspecialchars($protocol['sheriff_name']); ?></td>
                                    <td><?php echo formatDate($protocol['receiving_date']); ?></td>
                                    <td><?php echo formatDate($protocol['return_date']); ?></td>
                                    <td>
                                        <?php if ($protocol['status'] === 'active'): ?>
                                            <span class="badge bg-success text-white">Aktiv</span>
                                        <?php elseif ($protocol['status'] === 'returned'): ?>
                                            <span class="badge bg-secondary text-white">Zurückgegeben</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-white">Beweismittel</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-protocol" data-toggle="modal" data-target="#viewProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                            <span data-feather="eye"></span>
                                        </button>
                                        <?php if (isAdminSession()): ?>
                                            <button class="btn btn-sm btn-danger delete-protocol" data-toggle="modal" data-target="#deleteProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                                <span data-feather="trash-2"></span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <h4 class="mt-5">Beweismittel</h4>
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Art</th>
                            <th>TG-Nummer</th>
                            <th>Besitzer</th>
                            <th>Sheriff</th>
                            <th>Aufnahmezeitpunkt</th>
                            <th>Status</th>
                            <th>Angenommen von</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($protocols as $protocol): ?>
                            <?php if ($protocol['protocol_type'] === 'evidence' && $protocol['status'] !== 'returned'): ?>
                                <tr>
                                    <td><?php echo $protocol['protocol_type'] === 'weapon' ? 'Waffenrückgabe' : 'Beweismittel'; ?></td>
                                    <td><?php echo htmlspecialchars($protocol['owner_tg_number']); ?></td>
                                    <td><?php echo htmlspecialchars($protocol['owner_name']); ?></td>
                                    <td><?php echo htmlspecialchars($protocol['sheriff_name']); ?></td>
                                    <td><?php echo formatDate($protocol['receiving_date']); ?></td>
                                    <td>
                                        <span class="badge bg-info text-white">Beweismittel</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($protocol['receiving_staff_name']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-protocol" data-toggle="modal" data-target="#viewProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                            <span data-feather="eye"></span>
                                        </button>
                                        <button class="btn btn-sm btn-warning edit-protocol" data-toggle="modal" data-target="#editProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                            <span data-feather="edit-2"></span>
                                        </button>
                                        <?php if (isAdminSession()): ?>
                                            <button class="btn btn-sm btn-danger delete-protocol" data-toggle="modal" data-target="#deleteProtocolModal" data-id="<?php echo $protocol['id']; ?>">
                                                <span data-feather="trash-2"></span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Modal: Neues Waffenrückgabeprotokoll -->
<div class="modal fade" id="newWeaponProtocolModal" tabindex="-1" role="dialog" aria-labelledby="newWeaponProtocolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newWeaponProtocolModalLabel">Neues Waffenrückgabeprotokoll</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="evidence.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_protocol">
                    <input type="hidden" name="protocol_type" value="weapon">
                    
                    <div class="form-group">
                        <label for="owner_name">Name des Besitzers:</label>
                        <input type="text" class="form-control" id="owner_name" name="owner_name" required>
                        <input type="hidden" id="owner_id" name="owner_id" value="">
                    </div>
                    
                    <div class="form-group">
                        <label for="owner_tg_number">TG-Nummer des Besitzers:</label>
                        <input type="text" class="form-control" id="owner_tg_number" name="owner_tg_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sheriff_name">Sheriff-Name:</label>
                        <input type="text" class="form-control" id="sheriff_name" name="sheriff_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="sheriff_id">Sheriff TG-Nummer:</label>
                        <input type="text" class="form-control" id="sheriff_id" name="sheriff_id" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confiscation_date">Datum der Beschlagnahme:</label>
                        <input type="date" class="form-control" id="confiscation_date" name="confiscation_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="retention_days">Aufbewahrungsdauer (Tage):</label>
                        <input type="number" class="form-control" id="retention_days" name="retention_days" min="1" value="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Waffen:</label>
                        <div id="weapons-container">
                            <div class="row weapon-entry mb-2">
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="item_name[]" placeholder="Waffenname" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="item_serial[]" placeholder="Seriennummer">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="add-weapon">Weitere Waffe hinzufügen</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="remarks">Bemerkungen:</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Protokoll erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Neue Beweismittelsicherung -->
<div class="modal fade" id="newEvidenceProtocolModal" tabindex="-1" role="dialog" aria-labelledby="newEvidenceProtocolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newEvidenceProtocolModalLabel">Neue Beweismittelsicherung</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="evidence.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_protocol">
                    <input type="hidden" name="protocol_type" value="evidence">
                    
                    <div class="form-group">
                        <label for="evidence_owner_name">Name des Besitzers:</label>
                        <input type="text" class="form-control" id="evidence_owner_name" name="owner_name" required>
                        <input type="hidden" id="evidence_owner_id" name="owner_id" value="">
                    </div>
                    
                    <div class="form-group">
                        <label for="evidence_owner_tg_number">TG-Nummer des Besitzers:</label>
                        <input type="text" class="form-control" id="evidence_owner_tg_number" name="owner_tg_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="evidence_sheriff_name">Sheriff-Name:</label>
                        <input type="text" class="form-control" id="evidence_sheriff_name" name="sheriff_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="evidence_sheriff_id">Sheriff TG-Nummer:</label>
                        <input type="text" class="form-control" id="evidence_sheriff_id" name="sheriff_id" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="evidence_confiscation_date">Datum der Beschlagnahme:</label>
                        <input type="date" class="form-control" id="evidence_confiscation_date" name="confiscation_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Beweismittel:</label>
                        <div id="evidence-container">
                            <div class="row evidence-entry mb-2">
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="item_name[]" placeholder="Bezeichnung" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="item_serial[]" placeholder="Identifikationsnummer (falls vorhanden)">
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="add-evidence">Weiteres Beweismittel hinzufügen</button>
                    </div>
                    
                    <div class="form-group">
                        <label for="evidence_remarks">Bemerkungen:</label>
                        <textarea class="form-control" id="evidence_remarks" name="remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Protokoll erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Protokoll ansehen -->
<div class="modal fade" id="viewProtocolModal" tabindex="-1" role="dialog" aria-labelledby="viewProtocolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewProtocolModalLabel">Protokoll ansehen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="view-protocol-content">
                <!-- Wird dynamisch gefüllt -->
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Laden...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Protokoll bearbeiten -->
<div class="modal fade" id="editProtocolModal" tabindex="-1" role="dialog" aria-labelledby="editProtocolModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProtocolModalLabel">Protokoll bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="post" id="edit-protocol-form">
                <div class="modal-body" id="edit-protocol-content">
                    <!-- Wird dynamisch gefüllt -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Laden...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Waffen zurückgeben -->
<div class="modal fade" id="returnWeaponsModal" tabindex="-1" role="dialog" aria-labelledby="returnWeaponsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="returnWeaponsModalLabel">Waffen zurückgeben</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="evidence.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="return_weapons">
                    <input type="hidden" name="protocol_id" id="return-protocol-id">
                    <p>Sind Sie sicher, dass Sie die Waffen zurückgeben möchten? Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    <p>Die Rückgabe wird mit Ihrem Benutzernamen und dem aktuellen Zeitstempel protokolliert.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-success">Waffen zurückgeben</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Protokoll löschen -->
<div class="modal fade" id="deleteProtocolModal" tabindex="-1" role="dialog" aria-labelledby="deleteProtocolModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProtocolModalLabel">Protokoll löschen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_protocol">
                    <input type="hidden" name="protocol_id" id="delete-protocol-id">
                    <p>Sind Sie sicher, dass Sie dieses Protokoll löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    <p class="text-danger"><strong>Hinweis:</strong> Nur Administratoren können Protokolle löschen.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Protokoll löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamisches Hinzufügen von Waffen
    document.getElementById('add-weapon').addEventListener('click', function() {
        const container = document.getElementById('weapons-container');
        const newRow = document.createElement('div');
        newRow.className = 'row weapon-entry mb-2';
        newRow.innerHTML = `
            <div class="col-md-6">
                <input type="text" class="form-control" name="item_name[]" placeholder="Waffenname" required>
            </div>
            <div class="col-md-6">
                <input type="text" class="form-control" name="item_serial[]" placeholder="Seriennummer">
            </div>
        `;
        container.appendChild(newRow);
    });
    
    // Dynamisches Hinzufügen von Beweismitteln
    document.getElementById('add-evidence').addEventListener('click', function() {
        const container = document.getElementById('evidence-container');
        const newRow = document.createElement('div');
        newRow.className = 'row evidence-entry mb-2';
        newRow.innerHTML = `
            <div class="col-md-6">
                <input type="text" class="form-control" name="item_name[]" placeholder="Bezeichnung" required>
            </div>
            <div class="col-md-6">
                <input type="text" class="form-control" name="item_serial[]" placeholder="Identifikationsnummer (falls vorhanden)">
            </div>
        `;
        container.appendChild(newRow);
    });
    
    // Waffen zurückgeben Button
    const returnButtons = document.querySelectorAll('.return-weapons');
    returnButtons.forEach(button => {
        button.addEventListener('click', function() {
            const protocolId = this.getAttribute('data-id');
            document.getElementById('return-protocol-id').value = protocolId;
        });
    });
    
    // Protokoll löschen Button
    const deleteButtons = document.querySelectorAll('.delete-protocol');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const protocolId = this.getAttribute('data-id');
            document.getElementById('delete-protocol-id').value = protocolId;
        });
    });
    
    // Protokoll ansehen Button
    const viewButtons = document.querySelectorAll('.view-protocol');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const protocolId = this.getAttribute('data-id');
            loadProtocolDetails(protocolId, 'view');
        });
    });
    
    // Protokoll bearbeiten Button
    const editButtons = document.querySelectorAll('.edit-protocol');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const protocolId = this.getAttribute('data-id');
            loadProtocolDetails(protocolId, 'edit');
        });
    });
    
    // Funktion zum Laden der Protokolldetails
    function loadProtocolDetails(protocolId, mode) {
        // In einer echten Anwendung würde hier ein AJAX-Aufruf erfolgen
        // Für diese Demo laden wir die Daten direkt aus dem versteckten DOM-Element
        
        // Protokolldaten finden
        const protocols = <?php echo json_encode($protocols); ?>;
        const protocol = protocols.find(p => p.id === protocolId);
        
        if (!protocol) {
            alert('Protokoll nicht gefunden!');
            return;
        }
        
        // Standardwert für Aufbewahrungsdauer setzen, falls nicht vorhanden
        if (protocol.protocol_type === 'weapon' && !protocol.retention_days) {
            protocol.retention_days = 1;
        }
        
        if (mode === 'view') {
            // Ansichtsmodus - Details anzeigen
            let content = `
                <div class="card mb-3">
                    <div class="card-header">
                        <h5>${protocol.protocol_type === 'weapon' ? 'Waffenrückgabeprotokoll' : 'Beweismittelsicherung'}</h5>
                        <span class="badge ${protocol.status === 'active' ? 'bg-success' : (protocol.status === 'returned' ? 'bg-secondary' : 'bg-info')} text-white">
                            ${protocol.status === 'active' ? 'Aktiv' : (protocol.status === 'returned' ? 'Zurückgegeben' : 'Beweismittel')}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Besitzer:</strong> ${protocol.owner_name}</p>
                                <p><strong>TG-Nummer des Besitzers:</strong> ${protocol.owner_tg_number}</p>
                                <p><strong>Sheriff:</strong> ${protocol.sheriff_name}</p>
                                <p><strong>Sheriff TG-Nummer:</strong> ${protocol.sheriff_id}</p>
                                <p><strong>Beschlagnahmedatum:</strong> ${new Date(protocol.confiscation_date).toLocaleDateString()}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Angenommen von:</strong> ${protocol.receiving_staff_name}</p>
                                <p><strong>Aufnahmezeitpunkt:</strong> ${new Date(protocol.receiving_date).toLocaleString()}</p>
                                ${protocol.status === 'returned' ? `
                                    <p><strong>Zurückgegeben von:</strong> ${protocol.returning_staff_name}</p>
                                    <p><strong>Rückgabezeitpunkt:</strong> ${new Date(protocol.return_date).toLocaleString()}</p>
                                ` : ''}
                                ${protocol.protocol_type === 'weapon' ? `
                                    <p><strong>Aufbewahrungsdauer:</strong> ${protocol.retention_days} Tage</p>
                                    <p><strong>Geplante Rückgabe:</strong> ${new Date(protocol.planned_return_date).toLocaleDateString()}</p>
                                ` : ''}
                            </div>
                        </div>
                        
                        <h6 class="mt-4">Gegenstände:</h6>
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Bezeichnung</th>
                                    <th>Seriennummer / ID</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            protocol.items.forEach(item => {
                content += `
                    <tr>
                        <td>${item.name}</td>
                        <td>${item.serial || '-'}</td>
                    </tr>`;
            });
            
            content += `
                            </tbody>
                        </table>
                        
                        ${protocol.remarks ? `
                            <h6 class="mt-4">Bemerkungen:</h6>
                            <p>${protocol.remarks.replace(/\n/g, '<br>')}</p>
                        ` : ''}
                        
                        <h6 class="mt-4">Änderungsverlauf:</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Zeitpunkt</th>
                                    <th>Benutzer</th>
                                    <th>Aktion</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>`;
            
            if (protocol.change_log && protocol.change_log.length > 0) {
                protocol.change_log.forEach(log => {
                    content += `
                        <tr>
                            <td>${new Date(log.timestamp).toLocaleString()}</td>
                            <td>${log.username}</td>
                            <td>${log.action}</td>
                            <td>${log.details || '-'}</td>
                        </tr>`;
                });
            } else {
                content += `
                    <tr>
                        <td colspan="4" class="text-center">Keine Änderungen protokolliert</td>
                    </tr>`;
            }
            
            content += `
                            </tbody>
                        </table>
                    </div>
                </div>`;
            
            // Waffenrückgabe-Button für aktive Waffenprotokolle hinzufügen
            if (protocol.protocol_type === 'weapon' && protocol.status === 'active') {
                content += `
                <div class="mt-4">
                    <form action="" method="post">
                        <input type="hidden" name="action" value="return_weapon">
                        <input type="hidden" name="protocol_id" value="${protocol.id}">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-undo mr-1"></i> Waffe zurückgeben
                        </button>
                    </form>
                </div>`;
            }
            
            document.getElementById('view-protocol-content').innerHTML = content;
        } else if (mode === 'edit') {
            // Bearbeitungsmodus - Formular mit Daten füllen
            let content = `
                <input type="hidden" name="action" value="edit_protocol">
                <input type="hidden" name="protocol_id" value="${protocol.id}">
                <input type="hidden" name="protocol_type" value="${protocol.protocol_type}">
                
                <input type="hidden" name="owner_id" value="${protocol.owner_id}">
                
                <div class="form-group">
                    <label for="edit_owner_name">Name des Besitzers:</label>
                    <input type="text" class="form-control" id="edit_owner_name" name="owner_name" value="${protocol.owner_name}" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_owner_tg_number">TG-Nummer des Besitzers:</label>
                    <input type="text" class="form-control" id="edit_owner_tg_number" name="owner_tg_number" value="${protocol.owner_tg_number}" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_sheriff_name">Sheriff-Name:</label>
                    <input type="text" class="form-control" id="edit_sheriff_name" name="sheriff_name" value="${protocol.sheriff_name}" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_sheriff_id">Sheriff TG-Nummer:</label>
                    <input type="text" class="form-control" id="edit_sheriff_id" name="sheriff_id" value="${protocol.sheriff_id}" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_confiscation_date">Datum der Beschlagnahme:</label>
                    <input type="date" class="form-control" id="edit_confiscation_date" name="confiscation_date" value="${protocol.confiscation_date}" required>
                </div>`;
                
            if (protocol.protocol_type === 'weapon') {
                // Standardwert für Aufbewahrungsdauer setzen, falls nicht vorhanden
                const retentionDays = protocol.retention_days || 1;
                
                content += `
                <div class="form-group">
                    <label for="edit_retention_days">Aufbewahrungsdauer (Tage):</label>
                    <input type="number" class="form-control" id="edit_retention_days" name="retention_days" min="1" value="${retentionDays}" required>
                </div>`;
            }
            
            content += `
                <div class="form-group">
                    <label>Gegenstände:</label>
                    <div id="edit-items-container">`;
            
            protocol.items.forEach((item, index) => {
                content += `
                        <div class="row item-entry mb-2">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="item_name[]" value="${item.name}" placeholder="Bezeichnung" required>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="item_serial[]" value="${item.serial || ''}" placeholder="Seriennummer / ID">
                            </div>
                        </div>`;
            });
            
            content += `
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" id="edit-add-item">Weiteren Gegenstand hinzufügen</button>
                </div>
                
                <div class="form-group">
                    <label for="edit_remarks">Bemerkungen:</label>
                    <textarea class="form-control" id="edit_remarks" name="remarks" rows="3">${protocol.remarks || ''}</textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>Hinweis:</strong> Alle Änderungen werden im Änderungsverlauf protokolliert.
                </div>`;
            
            document.getElementById('edit-protocol-content').innerHTML = content;
            
            // Event-Listener für das Hinzufügen weiterer Gegenstände
            setTimeout(() => {
                document.getElementById('edit-add-item').addEventListener('click', function() {
                    const container = document.getElementById('edit-items-container');
                    const newRow = document.createElement('div');
                    newRow.className = 'row item-entry mb-2';
                    newRow.innerHTML = `
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="item_name[]" placeholder="Bezeichnung" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="item_serial[]" placeholder="Seriennummer / ID">
                        </div>
                    `;
                    container.appendChild(newRow);
                });
            }, 500);
        }
    }
});
</script>

<?php include '../includes/footer.php';
?>

<!-- Zusätzliche JavaScript für die Seite -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Daten für View Protocol Modal laden
        document.querySelectorAll('.view-protocol').forEach(function(button) {
            button.addEventListener('click', function() {
                const protocolId = this.getAttribute('data-id');
                // Hier die Funktion implementieren, um die Protokolldetails zu laden und das Modal zu befüllen
                // Das kann durch AJAX oder direkt durch PHP-Session-Variablen geschehen
            });
        });
        
        // Waffenrückgabe-Formular Handling
        document.querySelectorAll('.return-weapon').forEach(function(button) {
            button.addEventListener('click', function() {
                const protocolId = this.getAttribute('data-id');
                document.getElementById('returnWeaponId').value = protocolId;
            });
        });
        
        // Suchfunktion für Protokolle
        const searchInput = document.getElementById('searchInput');
        const searchButton = document.getElementById('searchButton');
        const protocolTypeFilter = document.getElementById('protocolTypeFilter');
        
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase();
            const protocolType = protocolTypeFilter.value;
            
            // Alle Protokoll-Zeilen durchgehen
            document.querySelectorAll('.protocol-row').forEach(function(row) {
                const ownerName = row.getAttribute('data-owner');
                const tgNumber = row.getAttribute('data-tg');
                const rowType = row.closest('table').id === 'weaponProtocolsTable' ? 'weapon' : 'evidence';
                
                // Prüfen, ob die Zeile dem Suchbegriff entspricht
                const matchesSearch = !searchTerm || 
                                      ownerName.includes(searchTerm) || 
                                      tgNumber.includes(searchTerm);
                
                // Prüfen, ob die Zeile dem ausgewählten Protokolltyp entspricht
                const matchesType = protocolType === 'all' || rowType === protocolType;
                
                // Zeile anzeigen oder verstecken
                row.style.display = (matchesSearch && matchesType) ? '' : 'none';
            });
            
            // Sichtbarkeit der Kartenbereiche steuern
            if (protocolType === 'weapon' || protocolType === 'all') {
                document.getElementById('weaponProtocolsCard').style.display = '';
            } else {
                document.getElementById('weaponProtocolsCard').style.display = 'none';
            }
            
            if (protocolType === 'evidence' || protocolType === 'all') {
                document.getElementById('evidenceProtocolsCard').style.display = '';
            } else {
                document.getElementById('evidenceProtocolsCard').style.display = 'none';
            }
        }
        
        // Event-Listener für die Suche
        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keyup', function(event) {
            if (event.key === 'Enter') {
                performSearch();
            }
        });
        
        // Event-Listener für den Filter
        protocolTypeFilter.addEventListener('change', performSearch);
    });
</script>