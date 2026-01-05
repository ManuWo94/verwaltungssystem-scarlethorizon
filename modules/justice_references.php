<?php
/**
 * Department of Justice - Records Management System
 * Justizreferenzen: Bußgeldkatalog, Justizhandbuch und Dienstleistungen
 */

// Sicherheitscheck
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';

// Permission: view Bußgeldkatalog/Justizreferenzen
checkPermissionOrDie('justice_references', 'view');

include_once('../includes/header.php');
include_once('../includes/document_importer.php');
include_once('../includes/sidebar.php');

// Hilfsfunktionen für Range-Eingaben
function parseRangeInput($value, $asInt = false) {
    $text = trim((string)$value);
    if ($text === '') {
        return [0, 0];
    }
    if (preg_match('/(\d+(?:[.,]\d+)?)(?:\s*(?:-|–|bis|to)\s*(\d+(?:[.,]\d+)?))?/i', $text, $m)) {
        $min = (float)str_replace(',', '.', $m[1]);
        $max = isset($m[2]) && $m[2] !== '' ? (float)str_replace(',', '.', $m[2]) : $min;
    } else {
        $min = $max = 0;
    }
    if ($asInt) {
        $min = (int)round($min);
        $max = (int)round($max);
    }
    return [$min, $max];
}

function formatRangeDisplay($min, $max) {
    if ($min === null && $max === null) return '';
    if ($max === $min) return $min;
    return $min . ' - ' . $max;
}

function normalizeFinesArray($fines) {
    if (!is_array($fines)) {
        return [];
    }

    return array_map(function($fine) {
        if (!isset($fine['amount'])) {
            $fine['amount'] = 0;
        }
        $fine['amount_min'] = isset($fine['amount_min']) ? (float)$fine['amount_min'] : (float)$fine['amount'];
        $fine['amount_max'] = isset($fine['amount_max']) ? (float)$fine['amount_max'] : $fine['amount_min'];
        $fine['amount'] = isset($fine['amount']) ? (float)$fine['amount'] : (float)$fine['amount_max'];
        if ($fine['amount'] === 0 && $fine['amount_max'] > 0) {
            $fine['amount'] = $fine['amount_max'];
        }
        $fine['community_service_hours'] = isset($fine['community_service_hours']) ? (int)$fine['community_service_hours'] : 0;
        $fine['community_service_hours_min'] = isset($fine['community_service_hours_min']) ? (int)$fine['community_service_hours_min'] : (int)$fine['community_service_hours'];
        $fine['community_service_hours_max'] = isset($fine['community_service_hours_max']) ? (int)$fine['community_service_hours_max'] : (int)$fine['community_service_hours'];
        $fine['prison_days'] = isset($fine['prison_days']) ? (int)$fine['prison_days'] : 0;
        $fine['prison_days_min'] = isset($fine['prison_days_min']) ? (int)$fine['prison_days_min'] : (int)$fine['prison_days'];
        $fine['prison_days_max'] = isset($fine['prison_days_max']) ? (int)$fine['prison_days_max'] : (int)$fine['prison_days'];
        return $fine;
    }, $fines);
}

// Bearbeitung des Bußgeldkatalogs
$message = '';
$messageType = '';

// Pfad zu JSON-Dateien
$fineCatalogFile = '../data/fine_catalog.json';
$servicesFile = '../data/services.json';

// Bußgeldkatalog laden oder erstellen
if (!file_exists($fineCatalogFile)) {
    // Standarddaten erstellen, wenn Datei nicht existiert
    $defaultFines = [
        [
            'id' => 1,
            'category' => 'Verkehr',
            'violation' => 'Überschreitung der Geschwindigkeit',
            'description' => 'Reiten oder Fahren mit überhöhter Geschwindigkeit innerhalb einer Ortschaft',
            'amount' => 5,
            'amount_min' => 5,
            'amount_max' => 5,
            'prison_days' => 0,
            'prison_days_min' => 0,
            'prison_days_max' => 0,
            'community_service_hours' => 0,
            'community_service_hours_min' => 0,
            'community_service_hours_max' => 0,
            'notes' => 'Gilt auch für Kutschen und andere Fahrzeuge'
        ],
        [
            'id' => 2,
            'category' => 'Ordnung',
            'violation' => 'Erregung öffentlichen Ärgernisses',
            'description' => 'Unangemessenes Verhalten in der Öffentlichkeit',
            'amount' => 10,
            'amount_min' => 10,
            'amount_max' => 10,
            'prison_days' => 0,
            'prison_days_min' => 0,
            'prison_days_max' => 0,
            'community_service_hours' => 0,
            'community_service_hours_min' => 0,
            'community_service_hours_max' => 0,
            'notes' => 'Nach Ermessen des Sheriffs'
        ],
        [
            'id' => 3,
            'category' => 'Waffen',
            'violation' => 'Unbefugtes Führen einer Waffe',
            'description' => 'Führen einer Waffe ohne entsprechende Berechtigung',
            'amount' => 15,
            'amount_min' => 15,
            'amount_max' => 15,
            'prison_days' => 1,
            'prison_days_min' => 1,
            'prison_days_max' => 1,
            'community_service_hours' => 0,
            'community_service_hours_min' => 0,
            'community_service_hours_max' => 0,
            'notes' => 'Waffe kann beschlagnahmt werden'
        ],
        [
            'id' => 4,
            'category' => 'Gewalttaten',
            'violation' => 'Körperverletzung',
            'description' => 'Vorsätzliche Körperverletzung ohne schwerwiegende Folgen',
            'amount' => 25,
            'amount_min' => 25,
            'amount_max' => 25,
            'prison_days' => 3,
            'prison_days_min' => 3,
            'prison_days_max' => 3,
            'community_service_hours' => 0,
            'community_service_hours_min' => 0,
            'community_service_hours_max' => 0,
            'notes' => 'Bei schweren Verletzungen höhere Strafe möglich'
        ],
        [
            'id' => 5,
            'category' => 'Eigentum',
            'violation' => 'Diebstahl',
            'description' => 'Entwendung fremden Eigentums',
            'amount' => 20,
            'amount_min' => 20,
            'amount_max' => 20,
            'prison_days' => 2,
            'prison_days_min' => 2,
            'prison_days_max' => 2,
            'community_service_hours' => 0,
            'community_service_hours_min' => 0,
            'community_service_hours_max' => 0,
            'notes' => 'Zusätzlich zur Rückgabe des gestohlenen Guts'
        ]
    ];
    file_put_contents($fineCatalogFile, json_encode($defaultFines, JSON_PRETTY_PRINT));
}

// Dienstleistungen laden oder erstellen
if (!file_exists($servicesFile)) {
    // Standarddaten erstellen, wenn Datei nicht existiert
    $defaultServices = [
        [
            'id' => 1,
            'service' => 'Ausstellung einer Heiratsurkunde',
            'description' => 'Rechtsgültige Bescheinigung einer Eheschließung',
            'price' => 5,
            'process_time' => '1 Tag',
            'notes' => 'Beide Ehepartner müssen anwesend sein'
        ],
        [
            'id' => 2,
            'service' => 'Grundbucheintrag',
            'description' => 'Eintragung oder Änderung im Grundbuch',
            'price' => 10,
            'process_time' => '3 Tage',
            'notes' => 'Eigentumsnachweis erforderlich'
        ],
        [
            'id' => 3,
            'service' => 'Waffenschein',
            'description' => 'Genehmigung zum Führen einer Waffe',
            'price' => 15,
            'process_time' => '5 Tage',
            'notes' => 'Nur für unbescholtene Bürger'
        ]
    ];
    file_put_contents($servicesFile, json_encode($defaultServices, JSON_PRETTY_PRINT));
}

// Daten laden
$fines = normalizeFinesArray(json_decode(file_get_contents($fineCatalogFile), true));
$services = json_decode(file_get_contents($servicesFile), true);

// Bearbeitung des Bußgeldkatalogs
if (isset($_POST['action']) && $_POST['action'] == 'edit_fine') {
    $fineId = isset($_POST['fine_id']) ? (int)$_POST['fine_id'] : 0;
    
    // Finde den Index des zu bearbeitenden Eintrags
    $fineIndex = -1;
    foreach ($fines as $index => $fine) {
        if ($fine['id'] == $fineId) {
            $fineIndex = $index;
            break;
        }
    }

    if ($fineIndex >= 0) {
        // Aktualisiere den Eintrag
        $fines[$fineIndex]['category'] = $_POST['category'];
        $fines[$fineIndex]['violation'] = $_POST['violation'];
        $fines[$fineIndex]['description'] = $_POST['description'];

        list($amountMin, $amountMax) = parseRangeInput($_POST['amount_range'] ?? '');
        list($prisonMin, $prisonMax) = parseRangeInput($_POST['prison_range'] ?? '', true);
        list($csMin, $csMax) = parseRangeInput($_POST['community_service_range'] ?? '', true);

        $fines[$fineIndex]['amount_min'] = $amountMin;
        $fines[$fineIndex]['amount_max'] = $amountMax;
        $fines[$fineIndex]['amount'] = $amountMax > 0 ? $amountMax : $amountMin;
        $fines[$fineIndex]['community_service_hours'] = $csMax > 0 ? $csMax : $csMin;
        $fines[$fineIndex]['community_service_hours_min'] = $csMin;
        $fines[$fineIndex]['community_service_hours_max'] = $csMax > 0 ? $csMax : $csMin;
        $fines[$fineIndex]['prison_days'] = $prisonMax > 0 ? $prisonMax : $prisonMin;
        $fines[$fineIndex]['prison_days_min'] = $prisonMin;
        $fines[$fineIndex]['prison_days_max'] = $prisonMax > 0 ? $prisonMax : $prisonMin;
        $fines[$fineIndex]['notes'] = $_POST['notes'];

        // Speichere die Änderungen
        file_put_contents($fineCatalogFile, json_encode($fines, JSON_PRETTY_PRINT));
        $message = 'Bußgeldeintrag erfolgreich aktualisiert.';
        $messageType = 'success';
    }
}

// Hinzufügen eines neuen Bußgeldeintrags
if (isset($_POST['action']) && $_POST['action'] == 'add_fine') {
    // Finde die höchste ID und erhöhe sie um 1
    $maxId = 0;
    foreach ($fines as $fine) {
        if ($fine['id'] > $maxId) {
            $maxId = $fine['id'];
        }
    }
    $newId = $maxId + 1;

    list($amountMin, $amountMax) = parseRangeInput($_POST['amount_range'] ?? '');
    list($prisonMin, $prisonMax) = parseRangeInput($_POST['prison_range'] ?? '', true);
    list($csMin, $csMax) = parseRangeInput($_POST['community_service_range'] ?? '', true);

    // Erstelle einen neuen Eintrag
    $newFine = [
        'id' => $newId,
        'category' => $_POST['category'],
        'violation' => $_POST['violation'],
        'description' => $_POST['description'],
        'amount' => $amountMax > 0 ? $amountMax : $amountMin,
        'amount_min' => $amountMin,
        'amount_max' => $amountMax,
        'prison_days' => $prisonMax > 0 ? $prisonMax : $prisonMin,
        'prison_days_min' => $prisonMin,
        'prison_days_max' => $prisonMax > 0 ? $prisonMax : $prisonMin,
        'community_service_hours' => $csMax > 0 ? $csMax : $csMin,
        'community_service_hours_min' => $csMin,
        'community_service_hours_max' => $csMax > 0 ? $csMax : $csMin,
        'notes' => $_POST['notes']
    ];

    // Füge den neuen Eintrag hinzu
    $fines[] = $newFine;

    // Speichere die Änderungen
    file_put_contents($fineCatalogFile, json_encode($fines, JSON_PRETTY_PRINT));
    $message = 'Neuer Bußgeldeintrag erfolgreich hinzugefügt.';
    $messageType = 'success';
}

// Löschen eines Bußgeldeintrags
if (isset($_GET['action']) && $_GET['action'] == 'delete_fine' && isset($_GET['id'])) {
    $fineId = (int)$_GET['id'];
    
    // Finde den Index des zu löschenden Eintrags
    $fineIndex = -1;
    foreach ($fines as $index => $fine) {
        if ($fine['id'] == $fineId) {
            $fineIndex = $index;
            break;
        }
    }

    if ($fineIndex >= 0) {
        // Nur Administratoren dürfen löschen
        if (isUserAdmin()) {
            // Lösche den Eintrag
            array_splice($fines, $fineIndex, 1);

            // Speichere die Änderungen
            file_put_contents($fineCatalogFile, json_encode($fines, JSON_PRETTY_PRINT));
            $message = 'Bußgeldeintrag erfolgreich gelöscht.';
            $messageType = 'success';
        } else {
            $message = 'Sie haben keine Berechtigung, Einträge zu löschen. Wenden Sie sich an einen Administrator.';
            $messageType = 'danger';
        }
    }
}

// Bearbeitung der Dienstleistungen
if (isset($_POST['action']) && $_POST['action'] == 'edit_service') {
    $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    
    // Finde den Index der zu bearbeitenden Dienstleistung
    $serviceIndex = -1;
    foreach ($services as $index => $service) {
        if ($service['id'] == $serviceId) {
            $serviceIndex = $index;
            break;
        }
    }

    if ($serviceIndex >= 0) {
        // Aktualisiere den Eintrag
        $services[$serviceIndex]['service'] = $_POST['service'];
        $services[$serviceIndex]['description'] = $_POST['description'];
        $services[$serviceIndex]['price'] = (float)$_POST['price'];
        $services[$serviceIndex]['process_time'] = $_POST['process_time'];
        $services[$serviceIndex]['notes'] = $_POST['notes'];

        // Speichere die Änderungen
        file_put_contents($servicesFile, json_encode($services, JSON_PRETTY_PRINT));
        $message = 'Dienstleistung erfolgreich aktualisiert.';
        $messageType = 'success';
    }
}

// Hinzufügen einer neuen Dienstleistung
if (isset($_POST['action']) && $_POST['action'] == 'add_service') {
    // Finde die höchste ID und erhöhe sie um 1
    $maxId = 0;
    foreach ($services as $service) {
        if ($service['id'] > $maxId) {
            $maxId = $service['id'];
        }
    }
    $newId = $maxId + 1;

    // Erstelle einen neuen Eintrag
    $newService = [
        'id' => $newId,
        'service' => $_POST['service'],
        'description' => $_POST['description'],
        'price' => (float)$_POST['price'],
        'process_time' => $_POST['process_time'],
        'notes' => $_POST['notes']
    ];

    // Füge den neuen Eintrag hinzu
    $services[] = $newService;

    // Speichere die Änderungen
    file_put_contents($servicesFile, json_encode($services, JSON_PRETTY_PRINT));
    $message = 'Neue Dienstleistung erfolgreich hinzugefügt.';
    $messageType = 'success';
}

// Löschen einer Dienstleistung
if (isset($_GET['action']) && $_GET['action'] == 'delete_service' && isset($_GET['id'])) {
    $serviceId = (int)$_GET['id'];
    
    // Finde den Index des zu löschenden Eintrags
    $serviceIndex = -1;
    foreach ($services as $index => $service) {
        if ($service['id'] == $serviceId) {
            $serviceIndex = $index;
            break;
        }
    }

    if ($serviceIndex >= 0) {
        // Nur Administratoren dürfen löschen
        if (isUserAdmin()) {
            // Lösche den Eintrag
            array_splice($services, $serviceIndex, 1);

            // Speichere die Änderungen
            file_put_contents($servicesFile, json_encode($services, JSON_PRETTY_PRINT));
            $message = 'Dienstleistung erfolgreich gelöscht.';
            $messageType = 'success';
        } else {
            $message = 'Sie haben keine Berechtigung, Einträge zu löschen. Wenden Sie sich an einen Administrator.';
            $messageType = 'danger';
        }
    }
}

// Verarbeitung des Imports für Bußgeldkatalog
if (isset($_POST['action']) && $_POST['action'] == 'import_fine_catalog') {
    if (isset($_FILES['catalog_file']) && $_FILES['catalog_file']['error'] == 0) {
        // Speichere die hochgeladene Datei
        $uploadResult = saveUploadedFile($_FILES['catalog_file'], '../uploads/');
        
        if ($uploadResult['success']) {
            // Importiere den Bußgeldkatalog
            $importResult = importFineCatalog($uploadResult['path'], isset($_POST['merge_catalog']));
            
            if ($importResult['success']) {
                $message = $importResult['message'];
                $messageType = 'success';
                
                // Neu laden der Bußgeldkatalog-Daten
                $fines = normalizeFinesArray(json_decode(file_get_contents($fineCatalogFile), true));
            } else {
                $message = 'Fehler beim Importieren des Bußgeldkatalogs: ' . $importResult['message'];
                $messageType = 'danger';
            }
        } else {
            $message = 'Fehler beim Hochladen der Datei: ' . $uploadResult['error'];
            $messageType = 'danger';
        }
    } elseif (isset($_POST['catalog_url']) && !empty($_POST['catalog_url'])) {
        // Importiere den Bußgeldkatalog aus der URL
        $importResult = importFineCatalog($_POST['catalog_url'], isset($_POST['merge_catalog']));
        
        if ($importResult['success']) {
            $message = $importResult['message'];
            $messageType = 'success';
            
            // Neu laden der Bußgeldkatalog-Daten
            $fines = normalizeFinesArray(json_decode(file_get_contents($fineCatalogFile), true));
        } else {
            $message = 'Fehler beim Importieren des Bußgeldkatalogs: ' . $importResult['message'];
            $messageType = 'danger';
        }
    } else {
        $message = 'Keine Datei oder URL angegeben.';
        $messageType = 'warning';
    }
}

// Verarbeitung des Imports für das Justizhandbuch
if (isset($_POST['action']) && $_POST['action'] == 'import_handbook') {
    if (isset($_FILES['handbook_file']) && $_FILES['handbook_file']['error'] == 0) {
        // Speichere die hochgeladene Datei
        $uploadResult = saveHandbookDocument($_FILES['handbook_file']);
        
        if ($uploadResult['success']) {
            $message = 'Dokument erfolgreich hochgeladen: ' . $uploadResult['original_name'];
            $messageType = 'success';
        } else {
            $message = 'Fehler beim Hochladen des Dokuments: ' . $uploadResult['error'];
            $messageType = 'danger';
        }
    } elseif (isset($_POST['handbook_url']) && !empty($_POST['handbook_url'])) {
        // Verarbeite den Google Docs Link
        $linkResult = processGoogleDocsLink($_POST['handbook_url'], $_POST['handbook_title'] ?? '');
        
        if ($linkResult['success']) {
            $message = $linkResult['message'];
            $messageType = 'success';
        } else {
            $message = 'Fehler beim Verarbeiten des Links: ' . $linkResult['error'];
            $messageType = 'danger';
        }
    } else {
        $message = 'Keine Datei oder URL angegeben.';
        $messageType = 'warning';
    }
}

// Löschen eines Handbuch-Links
if (isset($_GET['action']) && $_GET['action'] == 'delete_handbook_link' && isset($_GET['id'])) {
    if (isUserAdmin()) {
        if (deleteHandbookLink($_GET['id'])) {
            $message = 'Link erfolgreich gelöscht.';
            $messageType = 'success';
        } else {
            $message = 'Fehler beim Löschen des Links.';
            $messageType = 'danger';
        }
    } else {
        $message = 'Sie haben keine Berechtigung, Links zu löschen.';
        $messageType = 'danger';
    }
}

// Löschen einer Handbuch-Datei
if (isset($_GET['action']) && $_GET['action'] == 'delete_handbook_file' && isset($_GET['file'])) {
    if (isUserAdmin()) {
        $filePath = '../uploads/handbook/' . $_GET['file'];
        
        // Überprüfen, ob die Datei im erlaubten Verzeichnis liegt
        $realFilePath = realpath($filePath);
        $realUploadDir = realpath('../uploads/handbook');
        
        if ($realFilePath && strpos($realFilePath, $realUploadDir) === 0 && file_exists($filePath)) {
            if (unlink($filePath)) {
                $message = 'Datei erfolgreich gelöscht.';
                $messageType = 'success';
            } else {
                $message = 'Fehler beim Löschen der Datei.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Datei nicht gefunden oder Zugriff verweigert.';
            $messageType = 'danger';
        }
    } else {
        $message = 'Sie haben keine Berechtigung, Dateien zu löschen.';
        $messageType = 'danger';
    }
}

// Handbuch-Links laden
$handbookLinks = getHandbookLinks();

// Aktive Registerkarte basierend auf GET-Parameter festlegen
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'fines';
?>

<div class="container-fluid main-content">
    <div class="row">
        <div class="col-12">
            <h1>Justizreferenzen</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
                </div>
            <?php endif; ?>

            <!-- Tabs für die verschiedenen Inhalte -->
            <ul class="nav nav-tabs mb-4" id="justiceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $activeTab == 'fines' ? 'active' : ''; ?>" id="fines-tab" href="?tab=fines">Bußgeldkatalog</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $activeTab == 'handbook' ? 'active' : ''; ?>" id="handbook-tab" href="?tab=handbook">Justizhandbuch</a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $activeTab == 'services' ? 'active' : ''; ?>" id="services-tab" href="?tab=services">Dienstleistungen</a>
                </li>
            </ul>

            <div class="tab-content" id="justiceTabsContent">
                <!-- BUSSGELKATALOG -->
                <div class="tab-pane fade <?php echo $activeTab == 'fines' ? 'show active' : ''; ?>" id="fines" role="tabpanel" aria-labelledby="fines-tab">
                    <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Bußgeldkatalog importieren</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="?tab=fines" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_fine_catalog">
                                
                                <div class="mb-3">
                                    <label for="catalog_file" class="form-label">Datei hochladen (HTML, PDF)</label>
                                    <input type="file" class="form-control" id="catalog_file" name="catalog_file" accept=".html,.htm,.pdf">
                                    <div class="form-text">Unterstützte Dateiformate: HTML, PDF</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="catalog_url" class="form-label">Oder URL eingeben</label>
                                    <input type="url" class="form-control" id="catalog_url" name="catalog_url" placeholder="https://example.com/bussgeldkatalog.html">
                                    <div class="form-text">Geben Sie eine URL zu einem HTML- oder PDF-Dokument ein, oder zu einem Google Docs-Dokument</div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="merge_catalog" name="merge_catalog" checked>
                                    <label class="form-check-label" for="merge_catalog">Mit bestehendem Katalog zusammenführen</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Importieren</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between">
                            <h5 class="mb-0">Bußgeldkatalog</h5>
                            <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                                <button type="button" class="btn btn-primary btn-sm" id="addFineButton">
                                    <i class="fas fa-plus"></i> Neuen Eintrag hinzufügen
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kategorie</th>
                                            <th>Verstoß</th>
                                            <th>Beschreibung</th>
                                            <th>Bußgeld ($)</th>
                                            <th>Haftzeit (Tage)</th>
                                            <th>Strafarbeit (Std)</th>
                                            <th>Anmerkungen</th>
                                            <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                                                <th>Aktionen</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($fines as $fine): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($fine['category']); ?></td>
                                                <td><?php echo htmlspecialchars($fine['violation']); ?></td>
                                                <td><?php echo htmlspecialchars($fine['description']); ?></td>
                                                <td><?php echo htmlspecialchars(formatRangeDisplay($fine['amount_min'], $fine['amount_max'])); ?></td>
                                                <td><?php echo htmlspecialchars(formatRangeDisplay($fine['prison_days_min'] ?? $fine['prison_days'], $fine['prison_days_max'] ?? $fine['prison_days'])); ?></td>
                                                <td><?php echo htmlspecialchars(formatRangeDisplay($fine['community_service_hours_min'] ?? 0, $fine['community_service_hours_max'] ?? ($fine['community_service_hours_min'] ?? 0))); ?></td>
                                                <td><?php echo htmlspecialchars($fine['notes']); ?></td>
                                                <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                                                    <td>
                                                        <button type="button" class="btn btn-warning btn-sm edit-fine" 
                                                            data-id="<?php echo isset($fine['id']) ? $fine['id'] : ''; ?>"
                                                            data-category="<?php echo htmlspecialchars($fine['category']); ?>"
                                                            data-violation="<?php echo htmlspecialchars($fine['violation']); ?>"
                                                            data-description="<?php echo htmlspecialchars($fine['description']); ?>"
                                                            data-amount-range="<?php echo htmlspecialchars(formatRangeDisplay($fine['amount_min'], $fine['amount_max'])); ?>"
                                                            data-prison-range="<?php echo htmlspecialchars(formatRangeDisplay($fine['prison_days_min'] ?? $fine['prison_days'], $fine['prison_days_max'] ?? $fine['prison_days'])); ?>"
                                                            data-community-range="<?php echo htmlspecialchars(formatRangeDisplay($fine['community_service_hours_min'] ?? 0, $fine['community_service_hours_max'] ?? ($fine['community_service_hours_min'] ?? 0))); ?>"
                                                            data-notes="<?php echo htmlspecialchars($fine['notes']); ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (isUserAdmin() && isset($fine['id'])): ?>
                                                            <a href="?tab=fines&action=delete_fine&id=<?php echo htmlspecialchars($fine['id']); ?>" class="btn btn-danger btn-sm delete-fine" onclick="return confirm('Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- JUSTIZHANDBUCH -->
                <div class="tab-pane fade <?php echo $activeTab == 'handbook' ? 'show active' : ''; ?>" id="handbook" role="tabpanel" aria-labelledby="handbook-tab">
                    <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Justizhandbuch hinzufügen</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="?tab=handbook" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_handbook">
                                
                                <div class="mb-3">
                                    <label for="handbook_file" class="form-label">Datei hochladen (HTML, PDF)</label>
                                    <input type="file" class="form-control" id="handbook_file" name="handbook_file" accept=".html,.htm,.pdf">
                                    <div class="form-text">Unterstützte Dateiformate: HTML, PDF</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="handbook_url" class="form-label">Oder Google Docs URL eingeben</label>
                                    <input type="url" class="form-control" id="handbook_url" name="handbook_url" placeholder="https://docs.google.com/document/d/...">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="handbook_title" class="form-label">Titel (optional)</label>
                                    <input type="text" class="form-control" id="handbook_title" name="handbook_title" placeholder="Justizhandbuch Teil 1">
                                    <div class="form-text">Ein beschreibender Titel für das Dokument</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Hinzufügen</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Justizhandbücher</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($handbookLinks)): ?>
                                <!-- Aktuelles Justizhandbuch anzeigen als Link -->
                                <div class="d-flex justify-content-center">
                                    <a href="https://docs.google.com/document/d/1d769rK8QfH6VhWGGO4sQaFzf196xah77rO1URp-1mD8/edit?usp=sharing" target="_blank" class="btn btn-primary btn-lg mb-4">
                                        <i class="fas fa-external-link-alt"></i> Justizhandbuch öffnen
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- Liste der hinzugefügten Handbücher -->
                                <div class="list-group mb-4">
                                    <?php foreach ($handbookLinks as $link): ?>
                                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($link['title']); ?></h6>
                                                <p class="mb-1 text-muted small"><?php echo htmlspecialchars($link['url']); ?></p>
                                                <small>Hinzugefügt: <?php echo date('d.m.Y H:i', strtotime($link['date_added'])); ?></small>
                                            </div>
                                            <div>
                                                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="btn btn-sm btn-primary me-2">
                                                    <i class="fas fa-external-link-alt"></i> Öffnen
                                                </a>
                                                <?php if (isUserAdmin()): ?>
                                                    <a href="?tab=handbook&action=delete_handbook_link&id=<?php echo htmlspecialchars($link['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie diesen Link löschen möchten?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- Aktuelles Justizhandbuch anzeigen als Link -->
                                <div class="d-flex justify-content-center">
                                    <a href="https://docs.google.com/document/d/1d769rK8QfH6VhWGGO4sQaFzf196xah77rO1URp-1mD8/edit?usp=sharing" target="_blank" class="btn btn-primary btn-lg mb-4">
                                        <i class="fas fa-external-link-alt"></i> Justizhandbuch öffnen
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($handbookLinks)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Hochgeladene Dokumente</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $uploadedDocs = glob('../uploads/handbook/*.*');
                            if (empty($uploadedDocs)):
                            ?>
                                <p class="text-muted">Keine hochgeladenen Dokumente vorhanden.</p>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach ($uploadedDocs as $doc): 
                                        $fileName = basename($doc);
                                        $fileSize = filesize($doc);
                                        $fileExt = pathinfo($doc, PATHINFO_EXTENSION);
                                        
                                        // Formatieren der Dateigröße
                                        if ($fileSize < 1024) {
                                            $formattedSize = $fileSize . ' B';
                                        } elseif ($fileSize < 1024 * 1024) {
                                            $formattedSize = round($fileSize / 1024, 2) . ' KB';
                                        } else {
                                            $formattedSize = round($fileSize / (1024 * 1024), 2) . ' MB';
                                        }
                                    ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas <?php echo $fileExt == 'pdf' ? 'fa-file-pdf' : 'fa-file-alt'; ?> me-2"></i>
                                                <?php echo htmlspecialchars($fileName); ?>
                                                <span class="badge bg-secondary"><?php echo $formattedSize; ?></span>
                                            </div>
                                            <div>
                                                <a href="../uploads/handbook/<?php echo $fileName; ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Anzeigen
                                                </a>
                                                <?php if (isUserAdmin()): ?>
                                                    <a href="?tab=handbook&action=delete_handbook_file&file=<?php echo urlencode($fileName); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie diese Datei löschen möchten?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- DIENSTLEISTUNGEN -->
                <div class="tab-pane fade <?php echo $activeTab == 'services' ? 'show active' : ''; ?>" id="services" role="tabpanel" aria-labelledby="services-tab">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between">
                            <h5 class="mb-0">Dienstleistungen</h5>
                            <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                                <button type="button" class="btn btn-primary btn-sm" id="addServiceButton">
                                    <i class="fas fa-plus"></i> Neue Dienstleistung hinzufügen
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Dienstleistung</th>
                                            <th>Beschreibung</th>
                                            <th>Preis ($)</th>
                                            <th>Bearbeitungszeit</th>
                                            <th>Anmerkungen</th>
                                            <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                                                <th>Aktionen</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($services as $service): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($service['service']); ?></td>
                                                <td><?php echo htmlspecialchars($service['description']); ?></td>
                                                <td><?php echo htmlspecialchars($service['price']); ?></td>
                                                <td><?php echo htmlspecialchars($service['process_time']); ?></td>
                                                <td><?php echo htmlspecialchars($service['notes']); ?></td>
                                                <?php if (isUserAdmin() || hasUserRole('Richter')): ?>
                                                    <td>
                                                        <button type="button" class="btn btn-warning btn-sm edit-service" 
                                                            data-id="<?php echo $service['id']; ?>"
                                                            data-service="<?php echo htmlspecialchars($service['service']); ?>"
                                                            data-description="<?php echo htmlspecialchars($service['description']); ?>"
                                                            data-price="<?php echo htmlspecialchars($service['price']); ?>"
                                                            data-process-time="<?php echo htmlspecialchars($service['process_time']); ?>"
                                                            data-notes="<?php echo htmlspecialchars($service['notes']); ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (isUserAdmin()): ?>
                                                            <a href="?tab=services&action=delete_service&id=<?php echo htmlspecialchars($service['id']); ?>" class="btn btn-danger btn-sm delete-service" onclick="return confirm('Sind Sie sicher, dass Sie diese Dienstleistung löschen möchten?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal für das Bearbeiten eines Bußgeldeintrags -->
<div class="modal fade" id="editFineModal" tabindex="-1" aria-labelledby="editFineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFineModalLabel">Bußgeldeintrag bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form method="post" action="?tab=fines">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_fine">
                    <input type="hidden" name="fine_id" id="edit_fine_id">
                    
                    <div class="mb-3">
                        <label for="edit_category" class="form-label">Kategorie</label>
                        <input type="text" class="form-control" id="edit_category" name="category" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_violation" class="form-label">Verstoß</label>
                        <input type="text" class="form-control" id="edit_violation" name="violation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit_amount_range" class="form-label">Bußgeld (z.B. 5-10)</label>
                            <input type="text" class="form-control" id="edit_amount_range" name="amount_range" placeholder="5-10" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_prison_range" class="form-label">Haftzeit (Tage, z.B. 1-3)</label>
                            <input type="text" class="form-control" id="edit_prison_range" name="prison_range" placeholder="1-3">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_community_service_range" class="form-label">Strafarbeit (Std, z.B. 5-8)</label>
                            <input type="text" class="form-control" id="edit_community_service_range" name="community_service_range" placeholder="5-8">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">Anmerkungen</label>
                        <textarea class="form-control" id="edit_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal für das Hinzufügen eines Bußgeldeintrags -->
<div class="modal fade" id="addFineModal" tabindex="-1" aria-labelledby="addFineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFineModalLabel">Neuen Bußgeldeintrag hinzufügen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form method="post" action="?tab=fines">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_fine">
                    
                    <div class="mb-3">
                        <label for="add_category" class="form-label">Kategorie</label>
                        <input type="text" class="form-control" id="add_category" name="category" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_violation" class="form-label">Verstoß</label>
                        <input type="text" class="form-control" id="add_violation" name="violation" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="add_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="add_amount_range" class="form-label">Bußgeld (z.B. 5-10)</label>
                            <input type="text" class="form-control" id="add_amount_range" name="amount_range" placeholder="5-10" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="add_prison_range" class="form-label">Haftzeit (Tage, z.B. 1-3)</label>
                            <input type="text" class="form-control" id="add_prison_range" name="prison_range" placeholder="1-3">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="add_community_service_range" class="form-label">Strafarbeit (Std, z.B. 5-8)</label>
                            <input type="text" class="form-control" id="add_community_service_range" name="community_service_range" placeholder="5-8">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_notes" class="form-label">Anmerkungen</label>
                        <textarea class="form-control" id="add_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal für das Bearbeiten einer Dienstleistung -->
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editServiceModalLabel">Dienstleistung bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form method="post" action="?tab=services">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_service">
                    <input type="hidden" name="service_id" id="edit_service_id">
                    
                    <div class="mb-3">
                        <label for="edit_service_title" class="form-label">Dienstleistung</label>
                        <input type="text" class="form-control" id="edit_service_title" name="service" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_service_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="edit_service_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_price" class="form-label">Preis ($)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="edit_price" name="price" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="edit_process_time" class="form-label">Bearbeitungszeit</label>
                            <input type="text" class="form-control" id="edit_process_time" name="process_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_service_notes" class="form-label">Anmerkungen</label>
                        <textarea class="form-control" id="edit_service_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal für das Hinzufügen einer Dienstleistung -->
<div class="modal fade" id="addServiceModal" tabindex="-1" aria-labelledby="addServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addServiceModalLabel">Neue Dienstleistung hinzufügen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <form method="post" action="?tab=services">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_service">
                    
                    <div class="mb-3">
                        <label for="add_service_title" class="form-label">Dienstleistung</label>
                        <input type="text" class="form-control" id="add_service_title" name="service" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_service_description" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="add_service_description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_price" class="form-label">Preis ($)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="add_price" name="price" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="add_process_time" class="form-label">Bearbeitungszeit</label>
                            <input type="text" class="form-control" id="add_process_time" name="process_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_service_notes" class="form-label">Anmerkungen</label>
                        <textarea class="form-control" id="add_service_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript für die Modals und Bearbeitungsfunktionen
document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM geladen. Initialisiere Modals und Eventhandler...");
    
    // Wir nutzen jQuery, da es bereits in der Seite eingebunden ist
    $(document).ready(function() {
        console.log("jQuery bereit, initialisiere Modals...");
        
        // Buttons für Modals
        $('#addFineButton').click(function() {
            console.log("addFineButton geklickt");
            $('#addFineModal').modal('show');
        });
        
        $('#addServiceButton').click(function() {
            console.log("addServiceButton geklickt");
            $('#addServiceModal').modal('show');
        });
        
        // Bearbeitungs-Buttons
        $('.edit-fine').click(function() {
            console.log("edit-fine Button geklickt");
            
            try {
                // Daten aus den Attributen abrufen und in die Formularfelder einfügen
                $('#edit_fine_id').val($(this).data('id') || '');
                $('#edit_category').val($(this).data('category') || '');
                $('#edit_violation').val($(this).data('violation') || '');
                $('#edit_description').val($(this).data('description') || '');
                $('#edit_amount_range').val($(this).data('amountRange') || $(this).attr('data-amount-range') || '');
                $('#edit_prison_range').val($(this).data('prisonRange') || $(this).attr('data-prison-range') || '');
                $('#edit_community_service_range').val($(this).data('communityRange') || $(this).attr('data-community-range') || '');
                $('#edit_notes').val($(this).data('notes') || '');
                
                // Modal anzeigen
                $('#editFineModal').modal('show');
                console.log("editFineModal geöffnet");
            } catch (error) {
                console.error("Fehler beim Öffnen des Edit-Fine-Modals:", error);
            }
        });
        
        $('.edit-service').click(function() {
            console.log("edit-service Button geklickt");
            
            try {
                // Daten aus den Attributen abrufen und in die Formularfelder einfügen
                $('#edit_service_id').val($(this).data('id') || '');
                $('#edit_service_title').val($(this).data('service') || '');
                $('#edit_service_description').val($(this).data('description') || '');
                $('#edit_price').val($(this).data('price') || '0');
                $('#edit_process_time').val($(this).data('process-time') || '');
                $('#edit_service_notes').val($(this).data('notes') || '');
                
                // Modal anzeigen
                $('#editServiceModal').modal('show');
                console.log("editServiceModal geöffnet");
            } catch (error) {
                console.error("Fehler beim Öffnen des Edit-Service-Modals:", error);
            }
        });
        
        // Modal-Schließen-Buttons
        $('.modal .btn-secondary[data-bs-dismiss="modal"]').click(function() {
            console.log("Abbrechen-Button geklickt für Modal: " + $(this).closest('.modal').attr('id'));
            $(this).closest('.modal').modal('hide');
        });
        
        // Für alle Close-Buttons in Modals
        $('.modal .btn-close').click(function() {
            console.log("Close-Button geklickt für Modal: " + $(this).closest('.modal').attr('id'));
            $(this).closest('.modal').modal('hide');
        });
        
        console.log("jQuery Modal-Eventhandler erfolgreich initialisiert");
    });
});
</script>

<?php include_once('../includes/footer.php'); ?>