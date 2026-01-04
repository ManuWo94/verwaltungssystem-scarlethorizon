<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Gewerbeschein-Verwaltungsmodul (Neue Version)
 * 
 * Dieses Modul ermöglicht die Verwaltung von Unternehmensdaten, Pachtverträgen und Gewerbescheinen
 * inklusive Verlängerung, Statusüberwachung und Protokollierung aller Aktionen.
 * 
 * Funktionen:
 * - Verwaltung von Unternehmensdaten (Name, Inhaber, Stellvertreter, Kontaktdaten)
 * - Pachtvertragsverwaltung mit automatischer Berechnung der Ablaufdaten
 * - Überwachung der Gewerbeschein-Gültigkeit mit farbcodiertem Status
 * - Dokumentation und Protokollierung aller Maßnahmen und Benachrichtigungen
 * - Verwaltung von Unternehmenslizenzen mit individualisierbaren Ablaufdaten
 * - Upload und Vorschau von Pachtverträgen als PDF oder Bild
 * - Automatische Gewerbeschein-Generierung mit Benutzersignatur
 * - Kumulative Verlängerung der Gültigkeitsdauer
 */

// Lade essentielle Dateien
require_once '../includes/session_config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../pdf_generator.php';

/**
 * Bereinigt einen Dateinamen für sicheren Gebrauch im Dateisystem
 * 
 * @param string $filename Der zu bereinigende Dateiname
 * @return string Der bereinigte Dateiname
 */
function sanitizeFilename($filename) {
    // Entferne ungültige Zeichen
    $filename = preg_replace('/[^\p{L}\p{N}_\s\.-]/u', '', $filename);
    
    // Ersetze Leerzeichen durch Unterstriche
    $filename = str_replace(' ', '_', $filename);
    
    // Entferne mehrfache Unterstriche, Punkte oder Bindestriche
    $filename = preg_replace('/[_\.-]+/', '_', $filename);
    
    // Kürze zu lange Dateinamen
    if (strlen($filename) > 100) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $filenameWithoutExt = substr($filenameWithoutExt, 0, 95 - strlen($extension));
        $filename = $filenameWithoutExt . '.' . $extension;
    }
    
    // Wenn der Dateiname nach der Bereinigung leer ist, verwende einen Standardnamen
    if (empty($filename)) {
        $filename = 'file_' . uniqid();
    }
    
    return $filename;
}

/**
 * Prüft den MIME-Typ einer Datei und bestimmt, ob der Typ erlaubt ist
 * 
 * @param string $filePath Pfad zur Datei
 * @return array Array mit 'allowed' (bool) und 'mime' (string)
 */
function checkFileMimeType($filePath) {
    // Standardmäßig erlaubte MIME-Typen
    $allowedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg'
    ];
    
    // MIME-Typ der Datei bestimmen
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    // Prüfen, ob der MIME-Typ erlaubt ist
    $allowed = in_array($mime, $allowedMimeTypes);
    
    return [
        'allowed' => $allowed,
        'mime' => $mime
    ];
}

// Überprüfen, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$roles = $_SESSION['roles'] ?? [];

// Prüfen, ob der Benutzer Zugriffsrechte hat
if (!currentUserCan('business_licenses', 'view')) {
    include_once '../access_denied.php';
    exit;
}

// Datenpfade
$dataDir = '../data/';
$uploadsDir = '../uploads/business_licenses/';

// Stellen Sie sicher, dass das Upload-Verzeichnis existiert
if (!file_exists($uploadsDir) && !is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

// Stelle sicher, dass die Berechtigungen korrekt sind
if (file_exists($uploadsDir)) {
    chmod($uploadsDir, 0777);
}

$businessLicensesFile = $dataDir . 'business_licenses.json';
$businessLicensesLogFile = $dataDir . 'business_licenses_log.json';

// Daten laden
$businesses = getJsonData($businessLicensesFile);
if ($businesses === false) {
    $businesses = [];
}

$logEntries = getJsonData($businessLicensesLogFile);
if ($logEntries === false) {
    $logEntries = [];
}

// Aktuelle Benutzerinformationen für Signaturen abrufen
$userFullName = getUserFullName($user_id);

/**
 * Hilfsfunktion zum Protokollieren von Aktionen im Business-Lizenzsystem
 * 
 * @param string $businessId ID des betroffenen Unternehmens
 * @param string $businessName Name des betroffenen Unternehmens
 * @param string $action Art der Aktion (erstellt, bearbeitet, gelöscht, verlängert, etc.)
 * @param string $details Detaillierte Beschreibung der Aktion
 * @return bool Erfolg oder Misserfolg der Protokollierung
 */
function logBusinessAction($businessId, $businessName, $action, $details = '') {
    global $businessLicensesLogFile, $logEntries, $user_id, $username;
    
    $logEntry = [
        'id' => generateUniqueId(),
        'business_id' => $businessId,
        'business_name' => $businessName,
        'action' => $action,
        'details' => $details,
        'user_id' => $user_id,
        'username' => $username,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Log-Eintrag am Anfang des Arrays hinzufügen (neueste Einträge zuerst)
    array_unshift($logEntries, $logEntry);
    
    return saveJsonData($businessLicensesLogFile, $logEntries);
}

/**
 * Findet ein Unternehmen anhand seiner ID
 * 
 * @param array $businesses Array aller Unternehmen
 * @param string $targetId ID des gesuchten Unternehmens
 * @return array|null Das gefundene Unternehmen oder null
 */
function findBusinessById($businesses, $targetId) {
    if (empty($targetId)) {
        error_log('Leere Business-ID übergeben');
        return null;
    }
    
    $targetId = trim((string)$targetId);
    error_log("Suche nach Business mit ID: '$targetId'");
    
    foreach ($businesses as $business) {
        if (!isset($business['id'])) {
            error_log('Business ohne ID gefunden, überspringe');
            continue;
        }
        
        $businessId = trim((string)$business['id']);
        
        // Detaillierte Protokollierung für Debugging
        error_log("Vergleiche Business IDs - aktuell: '$businessId' mit Ziel: '$targetId'");
        
        if ($businessId === $targetId) {
            error_log("Business gefunden: " . ($business['name'] ?? 'unbekannt'));
            return $business;
        }
    }
    
    error_log("Kein Business mit ID '$targetId' gefunden");
    return null;
}

/**
 * Berechnet den Status eines Gewerbescheins basierend auf dem Ablaufdatum
 * 
 * @param string $expiryDate Ablaufdatum im Format 'Y-m-d'
 * @return array Status-Informationen (status, days_remaining, text, color_class, needs_action)
 */
function calculateLicenseStatus($expiryDate) {
    // Aktuelles Datum mit Uhrzeit 00:00:00
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    try {
        // Sicherstellen, dass das Ablaufdatum ein gültiges Datum ist
        $expiry = new DateTime($expiryDate);
        $expiry->setTime(0, 0, 0);
        
        // Differenz zwischen heute und Ablaufdatum berechnen
        $interval = $today->diff($expiry);
        
        // Negative Tage, wenn das Ablaufdatum in der Vergangenheit liegt
        $daysRemaining = $interval->invert ? -$interval->days : $interval->days;
        
        // Debug-Protokoll
        error_log("Ablaufdatum: $expiryDate, Heute: " . $today->format('Y-m-d') . ", Tage verbleibend: $daysRemaining");
        
        // Normale Gültigkeit (grün): mehr als 7 Tage verbleibend
        if ($daysRemaining > 7) {
            return [
                'status' => 'green',
                'days_remaining' => $daysRemaining,
                'text' => 'Gültig',
                'color_class' => 'bg-success',
                'needs_action' => false
            ];
        } 
        // Vorwarnphase (gelb): 7 oder weniger Tage verbleibend
        else if ($daysRemaining >= 0) {
            return [
                'status' => 'yellow',
                'days_remaining' => $daysRemaining,
                'text' => 'Vorwarnung',
                'color_class' => 'bg-warning',
                'needs_action' => true
            ];
        } 
        // Nachfrist (rot): Bis zu 28 Tage nach Ablauf
        else if ($daysRemaining >= -28) {
            return [
                'status' => 'red',
                'days_remaining' => $daysRemaining,
                'text' => 'Nachfrist',
                'color_class' => 'bg-danger',
                'needs_action' => true
            ];
        } 
        // Letzter Aufschub (schwarz): Zwischen 29 und 42 Tagen nach Ablauf
        else if ($daysRemaining >= -42) {
            return [
                'status' => 'black',
                'days_remaining' => $daysRemaining,
                'text' => 'Letzter Aufschub',
                'color_class' => 'bg-dark',
                'needs_action' => true
            ];
        } 
        // Abgelaufen (grau): Mehr als 42 Tage nach Ablauf
        else {
            return [
                'status' => 'expired',
                'days_remaining' => $daysRemaining,
                'text' => 'Abgelaufen',
                'color_class' => 'bg-secondary',
                'needs_action' => true
            ];
        }
    } catch (Exception $e) {
        error_log("Fehler bei der Statusberechnung: " . $e->getMessage());
        
        // Fallback bei ungültigem Datum
        return [
            'status' => 'unknown',
            'days_remaining' => 0,
            'text' => 'Unbekannt',
            'color_class' => 'bg-secondary',
            'needs_action' => true
        ];
    }
}

/**
 * Berechnet das Ablaufdatum eines Gewerbescheins
 * 
 * @param string $startDate Startdatum im Format 'Y-m-d'
 * @param int $remainingDays Verbleibende Tage von vorherigem Gewerbeschein (für kumulative Verlängerung)
 * @return string Ablaufdatum im Format 'Y-m-d'
 */
function calculateLicenseExpiryDate($startDate, $remainingDays = 0) {
    try {
        // Validiere Eingabeparameter
        if (empty($startDate)) {
            error_log("calculateLicenseExpiryDate: Leeres Startdatum übergeben");
            $startDate = date('Y-m-d'); // Heutiges Datum als Fallback
        }
        
        // Validiere das Datumsformat
        if (!DateTime::createFromFormat('Y-m-d', $startDate)) {
            error_log("calculateLicenseExpiryDate: Ungültiges Datumsformat für '$startDate', verwende heutiges Datum");
            $startDate = date('Y-m-d');
        }
        
        $date = new DateTime($startDate);
        $date->setTime(0, 0, 0); // Setze Zeit auf 00:00:00
        
        // Debug-Info für Eingabeparameter
        error_log("calculateLicenseExpiryDate - Startdatum: $startDate, Verbleibende Tage: $remainingDays");
        
        // Immer 28 Tage Basislaufzeit
        $date->modify('+28 days');
        
        // Füge verbleibende Tage hinzu, wenn vorhanden und positiv
        if ($remainingDays > 0) {
            $date->modify('+' . intval($remainingDays) . ' days');
        }
        
        $expiryDate = $date->format('Y-m-d');
        error_log("calculateLicenseExpiryDate - Berechnetes Ablaufdatum: $expiryDate");
        
        return $expiryDate;
    } catch (Exception $e) {
        error_log("Fehler bei der Ablaufdatumsberechnung: " . $e->getMessage());
        
        // Fallback bei Fehler: 28 Tage ab heute
        $date = new DateTime();
        $date->modify('+28 days');
        return $date->format('Y-m-d');
    }
}

/**
 * Berechnet die verbleibenden Tage eines Gewerbescheins
 * 
 * @param string $expiryDate Ablaufdatum im Format 'Y-m-d'
 * @return int Anzahl der verbleibenden Tage (0 wenn abgelaufen)
 */
function calculateRemainingDays($expiryDate) {
    try {
        if (empty($expiryDate)) {
            error_log("calculateRemainingDays: Leeres Ablaufdatum übergeben");
            return 0;
        }
        
        // Validiere das Datumsformat
        if (!DateTime::createFromFormat('Y-m-d', $expiryDate)) {
            error_log("calculateRemainingDays: Ungültiges Datumsformat für '$expiryDate'");
            return 0;
        }
        
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        $expiry = new DateTime($expiryDate);
        $expiry->setTime(0, 0, 0);
        
        // Debug-Info
        error_log("calculateRemainingDays - Ablaufdatum: $expiryDate, Heute: " . $today->format('Y-m-d'));
        
        // Differenz berechnen
        $interval = $today->diff($expiry);
        
        // Wenn das Datum in der Vergangenheit liegt, gib 0 zurück
        if ($interval->invert) {
            error_log("calculateRemainingDays: Datum liegt in der Vergangenheit, gebe 0 zurück");
            return 0;
        }
        
        error_log("calculateRemainingDays: Verbleibende Tage: " . $interval->days);
        return $interval->days;
    } catch (Exception $e) {
        error_log("Fehler bei der Berechnung verbleibender Tage: " . $e->getMessage());
        return 0;
    }
}

/**
 * Erzeugt einen HTML-Gewerbeschein mit allen wichtigen Daten
 * 
 * @param array $business Unternehmensdaten
 * @return string HTML für den Gewerbeschein
 */
function generateBusinessCertificate($business) {
    global $userFullName;
    
    // Sicherstellen, dass alle erforderlichen Felder existieren
    $name = $business['name'] ?? 'Unbekanntes Unternehmen';
    $ownerName = $business['owner_name'] ?? 'Unbekannt';
    $deputyName = $business['deputy_name'] ?? '';
    $foundationDate = isset($business['foundation_date']) ? 
                    date('d.m.1899', strtotime($business['foundation_date'])) : 
                    date('d.m.1899');
    $expiryDate = isset($business['license_expiry']) ? 
                date('d.m.1899', strtotime($business['license_expiry'])) : 
                date('d.m.1899', strtotime('+28 days'));
    $issueDate = date('d.m.1899');
    
    // Status berechnen
    $status = isset($business['status']) ? $business['status'] : 
              calculateLicenseStatus($business['license_expiry'] ?? date('Y-m-d', strtotime('+28 days')));
    $isValid = in_array($status['status'] ?? '', ['green', 'yellow']);
    
    // Lizenzen formatieren
    $licensesHtml = '';
    if (!empty($business['licenses'])) {
        $licensesHtml .= "<h2>Lizenzen</h2>";
        foreach ($business['licenses'] as $license) {
            $licenseName = htmlspecialchars($license['name'] ?? 'Unbekannte Lizenz');
            $licenseDesc = htmlspecialchars($license['description'] ?? '');
            $licenseExpiry = isset($license['expiry_date']) ? 
                            date('d.m.1899', strtotime($license['expiry_date'])) : 
                            'Unbegrenzt';
            
            $licensesHtml .= "<p><strong>" . $licenseName . ":</strong> Gültig bis " . $licenseExpiry . "</p>";
            if (!empty($licenseDesc)) {
                $licensesHtml .= "<p><em>" . $licenseDesc . "</em></p>";
            }
        }
    }
    
    // HTML für den Gewerbeschein generieren
    $html = '<!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Gewerbeschein - ' . htmlspecialchars($name) . '</title>
        <style>
            body {
                font-family: "Times New Roman", Times, serif;
                color: #000;
                line-height: 1.6;
                margin: 0;
                padding: 0;
                background-color: #f9f7f0;
            }
            .certificate {
                max-width: 800px;
                margin: 20px auto;
                padding: 40px;
                background-color: #fff;
                border: 1px solid #000;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }
            .header h1 {
                font-size: 28px;
                margin: 0;
                padding: 10px 0;
            }
            .header h2 {
                font-size: 18px;
                margin: 5px 0;
                font-weight: normal;
            }
            .content {
                margin-bottom: 40px;
            }
            .content h2 {
                font-size: 20px;
                border-bottom: 1px solid #ccc;
                padding-bottom: 5px;
                margin-top: 25px;
            }
            .content p {
                margin: 10px 0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            table, th, td {
                border: 1px solid #000;
            }
            th, td {
                padding: 10px;
                text-align: left;
            }
            th {
                background-color: #f2f2f2;
            }
            .footer {
                margin-top: 50px;
                text-align: center;
                font-size: 14px;
            }
            .signature {
                margin-top: 60px;
                text-align: right;
            }
            .signature p {
                margin: 5px 0;
            }
            .copyable-text {
                white-space: pre-wrap;
                user-select: all;
                font-family: "Times New Roman", Times, serif;
                line-height: 1.6;
                margin-top: 50px;
                padding: 20px;
                border: 1px dashed #ccc;
                background-color: #f9f9f9;
            }
            .valid { color: green; }
            .invalid { color: red; }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">
                <h1>Department of Justice</h1>
                <h2>Amtlicher Gewerbeschein</h2>
                <p>Registrierungsnummer: ' . htmlspecialchars($business['id'] ?? 'Unbekannt') . '</p>
            </div>
            
            <div class="content">
                <h2>Unternehmensdaten</h2>
                <p><strong>Unternehmensname:</strong> ' . htmlspecialchars($name) . '</p>
                <p><strong>Gründungsdatum:</strong> ' . $foundationDate . '</p>
                <p><strong>Geschäftsführer:</strong> ' . htmlspecialchars($ownerName) . '</p>
                ' . ($deputyName ? '<p><strong>Stellvertreter:</strong> ' . htmlspecialchars($deputyName) . '</p>' : '') . '
                
                <h2>Informationen zum Gewerbeschein</h2>
                <p><strong>Ausstellungsdatum:</strong> ' . $issueDate . '</p>
                <p><strong>Gültig bis:</strong> ' . $expiryDate . '</p>
                <p><strong>Status:</strong> <span class="' . ($isValid ? 'valid' : 'invalid') . '">' . ($isValid ? 'Gültig' : 'Abgelaufen') . '</span></p>
                <p><em>Hiermit wird bestätigt, dass die Pachtgebühren bezahlt wurden und die Pacht bis zum ' . $expiryDate . ' gültig ist.</em></p>
                
                ' . $licensesHtml . '
            </div>
            
            <div class="signature">
                <p>Ausgestellt am ' . $issueDate . '</p>
                <p>Department of Justice</p>
                <br>
                <p>_________________________</p>
                <p>' . htmlspecialchars($userFullName) . '</p>
            </div>
            
            <div class="footer">
                <p>Dieser Gewerbeschein ist ein amtliches Dokument des Department of Justice. Fälschung und Missbrauch werden strafrechtlich verfolgt.</p>
            </div>
            
            <div class="copyable-text">Department of Justice


Amtlicher Gewerbeschein
Registrierungsnummer: ' . htmlspecialchars($business['id'] ?? '') . '

Unternehmensname: ' . htmlspecialchars($name) . '
Gründungsdatum: ' . $foundationDate . '
Geschäftsführer: ' . htmlspecialchars($ownerName) . '
' . ($deputyName ? 'Stellvertreter: ' . htmlspecialchars($deputyName) : '') . '


Informationen zum Gewerbeschein:
Ausstellungsdatum: ' . $issueDate . '
Gültig bis: ' . $expiryDate . '

Hiermit wird bestätigt, dass die Pachtgebühren bezahlt wurden und die Pacht bis zum ' . $expiryDate . ' gültig ist.
Ausgestellt am ' . $issueDate . '

Department of Justice
' . htmlspecialchars($userFullName) . '
</div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Verarbeitung von Formular-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['action']) && !empty($_GET['action']))) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Neues Unternehmen anlegen
    if ($action === 'add_business' && currentUserCan('business_licenses', 'create')) {
        if (empty($_POST['business_name']) || empty($_POST['owner_name']) || empty($_POST['owner_telegram'])) {
            $error = 'Bitte füllen Sie alle Pflichtfelder aus.';
        } else {
            // Prüfen, ob der Unternehmensname bereits existiert
            $businessName = trim(sanitize($_POST['business_name']));
            $nameExists = false;
            
            foreach ($businesses as $existingBusiness) {
                if (strcasecmp($existingBusiness['name'], $businessName) === 0) {
                    $nameExists = true;
                    break;
                }
            }
            
            if ($nameExists) {
                $error = 'Ein Unternehmen mit diesem Namen existiert bereits.';
            } else {
                // Gründungsdatum prüfen und validieren
                $foundationDate = !empty($_POST['foundation_date']) ? 
                                 sanitize($_POST['foundation_date']) : 
                                 date('Y-m-d');
                
                // Startdatum des Pachtvertrags prüfen und validieren
                $licenseStartDate = !empty($_POST['license_start_date']) ? 
                                   sanitize($_POST['license_start_date']) : 
                                   date('Y-m-d');
                
                // Ablaufdatum berechnen (28 Tage ab Startdatum)
                $licenseExpiryDate = calculateLicenseExpiryDate($licenseStartDate);
                
                // Neues Unternehmen erstellen
                $businessId = generateUniqueId();
                $businessData = [
                    'id' => $businessId,
                    'name' => $businessName,
                    'owner_name' => sanitize($_POST['owner_name']),
                    'owner_telegram' => sanitize($_POST['owner_telegram']),
                    'deputy_name' => sanitize($_POST['deputy_name'] ?? ''),
                    'deputy_telegram' => sanitize($_POST['deputy_telegram'] ?? ''),
                    'foundation_date' => $foundationDate,
                    'license_start_date' => $licenseStartDate,
                    'license_renewal_date' => date('Y-m-d'),
                    'license_duration' => 1,   // Für Kompatibilität mit älteren Einträgen (ungefähr 1 Monat = 28 Tage)
                    'license_expiry' => $licenseExpiryDate,    // Berechnetes Ablaufdatum
                    'remaining_days_added' => 0,  // Keine verbleibenden Tage bei Neuerstellung
                    'notifications' => [
                        'pre_warning_sent' => false,
                        'expiry_notice_sent' => false
                    ],
                    'licenses' => [],
                    'attachments' => [], // Für hochgeladene Pachtverträge
                    'created_by' => $user_id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_by' => $user_id,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Pachtverträge hochladen, falls vorhanden
                if (isset($_FILES['contract_files']) && !empty($_FILES['contract_files']['name'][0])) {
                    $fileCount = count($_FILES['contract_files']['name']);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['contract_files']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['contract_files']['tmp_name'][$i];
                            $fileName = $_FILES['contract_files']['name'][$i];
                            $fileSize = $_FILES['contract_files']['size'][$i];
                            
                            // MIME-Typ prüfen
                            $mimeInfo = checkFileMimeType($tmpName);
                            
                            if ($mimeInfo['allowed']) {
                                // Dateinamen bereinigen und eindeutig machen
                                $safeFileName = sanitizeFilename($fileName);
                                $uniqueFileName = uniqid() . '_' . $safeFileName;
                                $targetPath = $uploadsDir . $uniqueFileName;
                                
                                if (move_uploaded_file($tmpName, $targetPath)) {
                                    // Erfolgreich hochgeladen, zur Anlage hinzufügen
                                    $businessData['attachments'][] = [
                                        'id' => generateUniqueId(),
                                        'file_name' => $uniqueFileName,
                                        'original_name' => $fileName,
                                        'mime_type' => $mimeInfo['mime'],
                                        'size' => $fileSize,
                                        'uploaded_at' => date('Y-m-d H:i:s'),
                                        'uploaded_by' => $user_id
                                    ];
                                } else {
                                    $error = 'Eine oder mehrere Anlagen konnten nicht hochgeladen werden.';
                                }
                            } else {
                                $error = 'Eine oder mehrere Anlagen haben einen nicht unterstützten Dateityp (nur PDF, PNG, JPEG erlaubt).';
                            }
                        } else if ($_FILES['contract_files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                            $error = 'Beim Hochladen einer oder mehrerer Anlagen ist ein Fehler aufgetreten.';
                        }
                    }
                }
                
                // Berechne Status für das neue Unternehmen
                $businessData['status'] = calculateLicenseStatus($businessData['license_expiry']);
                
                // Eintrag am Anfang der Liste hinzufügen
                array_unshift($businesses, $businessData);
                
                if (saveJsonData($businessLicensesFile, $businesses)) {
                    // Protokolliere die Erstellung des Unternehmens
                    logBusinessAction($businessData['id'], $businessData['name'], 'erstellt', 'Neues Unternehmen angelegt mit Gewerbeschein gültig bis ' . date('d.m.Y', strtotime($licenseExpiryDate)));
                    $message = 'Unternehmen wurde erfolgreich hinzugefügt.';
                } else {
                    $error = 'Fehler beim Speichern der Unternehmensdaten.';
                }
            }
        }
    }
    
    // Unternehmen bearbeiten
    elseif ($action === 'update_business' && isset($_POST['business_id']) && currentUserCan('business_licenses', 'edit')) {
        $businessId = sanitize($_POST['business_id']);
        $businessFound = false;
        
        foreach ($businesses as $key => $business) {
            // Verbesserte ID-Vergleichslogik mit Trim
            if (trim($business['id']) === trim($businessId)) {
                $businessFound = true;
                
                // Pflichtfelder prüfen
                if (empty($_POST['business_name']) || empty($_POST['owner_name']) || empty($_POST['owner_telegram'])) {
                    $error = 'Bitte füllen Sie alle Pflichtfelder aus.';
                } else {
                    // Prüfe, ob der Unternehmensname bereits existiert (außer beim aktuellen Unternehmen)
                    $businessName = trim(sanitize($_POST['business_name']));
                    $nameExists = false;
                    
                    foreach ($businesses as $existingBusiness) {
                        if ($existingBusiness['id'] !== $businessId && 
                            strcasecmp($existingBusiness['name'], $businessName) === 0) {
                            $nameExists = true;
                            break;
                        }
                    }
                    
                    if ($nameExists) {
                        $error = 'Ein anderes Unternehmen mit diesem Namen existiert bereits.';
                    } else {
                        // Änderungen protokollieren
                        $changes = [];
                        if ($business['name'] !== $businessName) {
                            $changes[] = "Name geändert von '{$business['name']}' zu '$businessName'";
                        }
                        
                        $ownerName = sanitize($_POST['owner_name']);
                        if ($business['owner_name'] !== $ownerName) {
                            $changes[] = "Inhaber geändert von '{$business['owner_name']}' zu '$ownerName'";
                        }
                        
                        // Aktualisierte Daten speichern
                        $businesses[$key]['name'] = $businessName;
                        $businesses[$key]['owner_name'] = $ownerName;
                        $businesses[$key]['owner_telegram'] = sanitize($_POST['owner_telegram']);
                        $businesses[$key]['deputy_name'] = sanitize($_POST['deputy_name'] ?? '');
                        $businesses[$key]['deputy_telegram'] = sanitize($_POST['deputy_telegram'] ?? '');
                        
                        // Optionale Datumsfelder nur aktualisieren, wenn sie angegeben wurden
                        if (!empty($_POST['foundation_date'])) {
                            $businesses[$key]['foundation_date'] = sanitize($_POST['foundation_date']);
                        }
                        
                        // Startdatum des Pachtvertrags aktualisieren
                        if (!empty($_POST['license_start_date'])) {
                            $licenseStartDate = sanitize($_POST['license_start_date']);
                            $businesses[$key]['license_start_date'] = $licenseStartDate;
                            
                            // Wenn das Startdatum geändert wurde, muss auch das Ablaufdatum neu berechnet werden
                            $remainingDays = isset($business['license_expiry']) ? calculateRemainingDays($business['license_expiry']) : 0;
                            $businesses[$key]['license_expiry'] = calculateLicenseExpiryDate($licenseStartDate, $remainingDays);
                            $businesses[$key]['status'] = calculateLicenseStatus($businesses[$key]['license_expiry']);
                            $changes[] = "Startdatum geändert auf '$licenseStartDate' und Ablaufdatum neu berechnet";
                        }
                        
                        // Metadaten aktualisieren
                        $businesses[$key]['updated_by'] = $user_id;
                        $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                        
                        // Pachtverträge hochladen, falls vorhanden
                        if (isset($_FILES['contract_files']) && !empty($_FILES['contract_files']['name'][0])) {
                            $fileCount = count($_FILES['contract_files']['name']);
                            
                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($_FILES['contract_files']['error'][$i] === UPLOAD_ERR_OK) {
                                    $tmpName = $_FILES['contract_files']['tmp_name'][$i];
                                    $fileName = $_FILES['contract_files']['name'][$i];
                                    $fileSize = $_FILES['contract_files']['size'][$i];
                                    
                                    // MIME-Typ prüfen
                                    $mimeInfo = checkFileMimeType($tmpName);
                                    
                                    if ($mimeInfo['allowed']) {
                                        // Dateinamen bereinigen und eindeutig machen
                                        $safeFileName = sanitizeFilename($fileName);
                                        $uniqueFileName = uniqid() . '_' . $safeFileName;
                                        $targetPath = $uploadsDir . $uniqueFileName;
                                        
                                        if (move_uploaded_file($tmpName, $targetPath)) {
                                            // Erfolgreich hochgeladen, zur Anlage hinzufügen
                                            $attachment = [
                                                'id' => generateUniqueId(),
                                                'file_name' => $uniqueFileName,
                                                'original_name' => $fileName,
                                                'mime_type' => $mimeInfo['mime'],
                                                'size' => $fileSize,
                                                'uploaded_at' => date('Y-m-d H:i:s'),
                                                'uploaded_by' => $user_id
                                            ];
                                            
                                            // Sicherstellen, dass das attachments-Array existiert
                                            if (!isset($businesses[$key]['attachments'])) {
                                                $businesses[$key]['attachments'] = [];
                                            }
                                            
                                            $businesses[$key]['attachments'][] = $attachment;
                                            $changes[] = "Neuer Pachtvertrag hochgeladen: $fileName";
                                        } else {
                                            $error = 'Eine oder mehrere Anlagen konnten nicht hochgeladen werden.';
                                        }
                                    } else {
                                        $error = 'Eine oder mehrere Anlagen haben einen nicht unterstützten Dateityp (nur PDF, PNG, JPEG erlaubt).';
                                    }
                                } else if ($_FILES['contract_files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                                    $error = 'Beim Hochladen einer oder mehrerer Anlagen ist ein Fehler aufgetreten.';
                                }
                            }
                        }
                        
                        // Speichern der Änderungen
                        if (saveJsonData($businessLicensesFile, $businesses)) {
                            // Protokolliere die Bearbeitung
                            logBusinessAction($business['id'], $businessName, 'bearbeitet', implode('; ', $changes));
                            $message = 'Unternehmen wurde erfolgreich aktualisiert.';
                        } else {
                            $error = 'Fehler beim Speichern der Unternehmensdaten.';
                        }
                    }
                }
                break;
            }
        }
        
        if (!$businessFound) {
            $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
        }
    }
    
    // Unternehmen löschen
    elseif ($action === 'delete_business' && isset($_POST['business_id']) && 
            (currentUserCan('business_licenses', 'delete') || checkUserHasRoleType(['chief_justice', 'senior_associate_justice']))) {
        $businessId = sanitize($_POST['business_id']);
        $businessFound = false;
        $businessName = '';
        
        foreach ($businesses as $key => $business) {
            if (trim($business['id']) === trim($businessId)) {
                $businessFound = true;
                $businessName = $business['name'];
                
                // Lösche alle zugehörigen Anhänge
                if (isset($business['attachments']) && is_array($business['attachments'])) {
                    foreach ($business['attachments'] as $attachment) {
                        if (isset($attachment['file_name'])) {
                            $filePath = $uploadsDir . $attachment['file_name'];
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                        }
                    }
                }
                
                // Entferne das Unternehmen aus dem Array
                array_splice($businesses, $key, 1);
                
                if (saveJsonData($businessLicensesFile, $businesses)) {
                    // Protokolliere die Löschung
                    logBusinessAction($businessId, $businessName, 'gelöscht', 'Unternehmen komplett gelöscht');
                    $message = 'Unternehmen wurde erfolgreich gelöscht.';
                } else {
                    $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                }
                break;
            }
        }
        
        if (!$businessFound) {
            $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
        }
    }
    
    // Gewerbeschein verlängern
    elseif ($action === 'renew_license' && isset($_POST['business_id']) && currentUserCan('business_licenses', 'edit')) {
        $businessId = sanitize($_POST['business_id']);
        error_log("Verlängerungsanfrage für Business-ID: $businessId");
        error_log("Anzahl der Unternehmen in der Datenbank: " . count($businesses));
        error_log("POST-Daten: " . print_r($_POST, true));
        
        $businessFound = false;
        
        foreach ($businesses as $key => $business) {
            $dbBusinessId = trim((string)$business['id']);
            $requestBusinessId = trim((string)$businessId);
            
            error_log("Vergleiche IDs - DB: '$dbBusinessId' (string) mit Request: '$requestBusinessId' (string)");
            
            if ($dbBusinessId === $requestBusinessId) {
                $businessFound = true;
                
                // Bei Verlängerung wird das ursprüngliche Startdatum beibehalten
                // Wenn kein Startdatum vorhanden ist, wird das aktuelle Datum verwendet
                $startDate = $business['license_start_date'] ?? date('Y-m-d');
                
                // Aktuelles Datum für die Erneuerungsaufzeichnung
                $renewalDate = date('Y-m-d');
                
                // Tage seit der letzten Verlängerung oder dem Startdatum berechnen
                $remainingDays = 0;
                
                // 1. Prüfen ob das Ablaufdatum in der Zukunft liegt (positive Tage übrig)
                if (isset($business['license_expiry'])) {
                    // Berechne verbleibende Tage (kann positiv oder negativ sein)
                    $expiryDate = new DateTime($business['license_expiry']);
                    $today = new DateTime();
                    $interval = $today->diff($expiryDate);
                    
                    // Wenn Ablaufdatum in der Zukunft liegt, addiere die verbleibenden Tage
                    if (!$interval->invert) {
                        $remainingDays = $interval->days;
                        error_log("Ablaufdatum liegt in der Zukunft, verbleibende Tage: $remainingDays");
                    } 
                    // Wenn Ablaufdatum in der Vergangenheit liegt, aber innerhalb der Nachfrist (28 Tage)
                    else if ($interval->days <= 28) {
                        $remainingDays = 0; // Keine Resttage, aber keine Strafe
                        error_log("Ablaufdatum liegt in der Vergangenheit, aber innerhalb der Nachfrist");
                    }
                    // Wenn Ablaufdatum weiter in der Vergangenheit liegt, keine Kumulation
                    else {
                        $remainingDays = 0;
                        error_log("Ablaufdatum liegt weit in der Vergangenheit, keine Kumulation möglich");
                    }
                }
                
                // Neues Ablaufdatum = Heute + 28 Tage + verbleibende Tage
                $newExpiryDate = date('Y-m-d', strtotime("+28 days +{$remainingDays} days"));
                error_log("Neues Ablaufdatum: $newExpiryDate (Heute + 28 Tage + $remainingDays Resttage)");
                
                // Status berechnen
                $status = calculateLicenseStatus($newExpiryDate);
                
                // Aktualisieren der Unternehmensdaten
                $businesses[$key]['license_start_date'] = $startDate;
                $businesses[$key]['license_renewal_date'] = $renewalDate;
                $businesses[$key]['license_expiry'] = $newExpiryDate;
                $businesses[$key]['status'] = $status;
                $businesses[$key]['remaining_days_added'] = $remainingDays;
                
                // Keine Benachrichtigungen mehr nötig nach Verlängerung
                $businesses[$key]['notifications'] = [
                    'pre_warning_sent' => false,
                    'expiry_notice_sent' => false
                ];
                
                // Metadaten aktualisieren
                $businesses[$key]['updated_by'] = $user_id;
                $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                
                if (saveJsonData($businessLicensesFile, $businesses)) {
                    // Protokolliere die Verlängerung
                    $details = "Gewerbeschein verlängert bis " . date('d.m.Y', strtotime($newExpiryDate));
                    if ($remainingDays > 0) {
                        $details .= " (inkl. $remainingDays Resttage)";
                    }
                    
                    logBusinessAction($business['id'], $business['name'], 'verlängert', $details);
                    $message = 'Gewerbeschein wurde erfolgreich verlängert.';
                } else {
                    $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                }
                break;
            }
        }
        
        if (!$businessFound) {
            $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
        }
    }
    
    // Benachrichtigung senden (markieren)
    elseif ($action === 'mark_notification' && isset($_POST['business_id']) && currentUserCan('business_licenses', 'edit')) {
        $businessId = sanitize($_POST['business_id']);
        $notificationType = sanitize($_POST['notification_type'] ?? '');
        
        if (!in_array($notificationType, ['pre_warning', 'expiry_notice'])) {
            $error = 'Ungültiger Benachrichtigungstyp.';
        } else {
            $businessFound = false;
            
            foreach ($businesses as $key => $business) {
                if (trim($business['id']) === trim($businessId)) {
                    $businessFound = true;
                    
                    // Sicherstellen, dass das notifications-Array existiert
                    if (!isset($businesses[$key]['notifications'])) {
                        $businesses[$key]['notifications'] = [
                            'pre_warning_sent' => false,
                            'expiry_notice_sent' => false
                        ];
                    }
                    
                    // Aktualisiere den entsprechenden Benachrichtigungsstatus
                    if ($notificationType === 'pre_warning') {
                        $businesses[$key]['notifications']['pre_warning_sent'] = true;
                        $notificationText = 'Vorwarnung';
                    } else {
                        $businesses[$key]['notifications']['expiry_notice_sent'] = true;
                        $notificationText = 'Ablaufhinweis';
                    }
                    
                    // Metadaten aktualisieren
                    $businesses[$key]['updated_by'] = $user_id;
                    $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (saveJsonData($businessLicensesFile, $businesses)) {
                        // Protokolliere die Benachrichtigung
                        logBusinessAction($business['id'], $business['name'], 'benachrichtigt', "Benachrichtigung ($notificationText) als gesendet markiert");
                        $message = 'Benachrichtigung wurde erfolgreich als gesendet markiert.';
                    } else {
                        $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                    }
                    break;
                }
            }
            
            if (!$businessFound) {
                $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
            }
        }
    }
    
    // Lizenz hinzufügen
    elseif ($action === 'add_license' && isset($_POST['business_id']) && currentUserCan('business_licenses', 'edit')) {
        $businessId = sanitize($_POST['business_id']);
        
        if (empty($_POST['license_name'])) {
            $error = 'Bitte geben Sie einen Namen für die Lizenz an.';
        } else {
            $businessFound = false;
            
            foreach ($businesses as $key => $business) {
                if (trim($business['id']) === trim($businessId)) {
                    $businessFound = true;
                    
                    // Neue Lizenz erstellen
                    $licenseId = generateUniqueId();
                    $licenseName = sanitize($_POST['license_name']);
                    $licenseDescription = sanitize($_POST['license_description'] ?? '');
                    
                    // Ablaufdatum der Lizenz (falls angegeben)
                    $licenseExpiryDate = !empty($_POST['license_expiry']) ? 
                                        sanitize($_POST['license_expiry']) : 
                                        calculateLicenseExpiryDate(date('Y-m-d'));
                    
                    $newLicense = [
                        'id' => $licenseId,
                        'name' => $licenseName,
                        'description' => $licenseDescription,
                        'issue_date' => date('Y-m-d'),
                        'created_by' => $user_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'expiry_date' => $licenseExpiryDate
                    ];
                    
                    // Status der Lizenz berechnen
                    $newLicense['status'] = calculateLicenseStatus($licenseExpiryDate);
                    
                    // Sicherstellen, dass das licenses-Array existiert
                    if (!isset($businesses[$key]['licenses'])) {
                        $businesses[$key]['licenses'] = [];
                    }
                    
                    // Neue Lizenz hinzufügen
                    $businesses[$key]['licenses'][] = $newLicense;
                    
                    // Metadaten aktualisieren
                    $businesses[$key]['updated_by'] = $user_id;
                    $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (saveJsonData($businessLicensesFile, $businesses)) {
                        // Protokolliere die Lizenzhinzufügung
                        logBusinessAction($business['id'], $business['name'], 'lizenz_hinzugefügt', 
                                         "Neue Lizenz '$licenseName' hinzugefügt, gültig bis " . date('d.m.Y', strtotime($licenseExpiryDate)));
                        $message = 'Lizenz wurde erfolgreich hinzugefügt.';
                    } else {
                        $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                    }
                    break;
                }
            }
            
            if (!$businessFound) {
                $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
            }
        }
    }
    
    // Lizenz löschen
    elseif ($action === 'delete_license' && isset($_POST['business_id']) && isset($_POST['license_id']) && currentUserCan('business_licenses', 'edit')) {
        $businessId = sanitize($_POST['business_id']);
        $licenseId = sanitize($_POST['license_id']);
        
        $businessFound = false;
        $licenseFound = false;
        
        foreach ($businesses as $key => $business) {
            if (trim($business['id']) === trim($businessId)) {
                $businessFound = true;
                
                if (isset($business['licenses']) && is_array($business['licenses'])) {
                    foreach ($business['licenses'] as $licenseKey => $license) {
                        if (isset($license['id']) && trim($license['id']) === trim($licenseId)) {
                            $licenseFound = true;
                            $licenseName = $license['name'] ?? 'Unbekannte Lizenz';
                            
                            // Lizenz entfernen
                            array_splice($businesses[$key]['licenses'], $licenseKey, 1);
                            
                            // Metadaten aktualisieren
                            $businesses[$key]['updated_by'] = $user_id;
                            $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                            
                            if (saveJsonData($businessLicensesFile, $businesses)) {
                                // Protokolliere die Lizenzlöschung
                                logBusinessAction($business['id'], $business['name'], 'lizenz_gelöscht', "Lizenz '$licenseName' gelöscht");
                                $message = 'Lizenz wurde erfolgreich gelöscht.';
                            } else {
                                $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                            }
                            break;
                        }
                    }
                }
                
                if (!$licenseFound) {
                    $error = 'Die angegebene Lizenz wurde nicht gefunden.';
                }
                break;
            }
        }
        
        if (!$businessFound) {
            $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
        }
    }
    
    // Pachtvertrag hochladen
    elseif ($action === 'upload_attachment' && isset($_POST['business_id']) && currentUserCan('business_licenses', 'edit')) {
        $businessId = sanitize($_POST['business_id']);
        error_log("Pachtvertrag-Upload für Business-ID: $businessId");
        
        $businessFound = false;
        
        foreach ($businesses as $key => $business) {
            if (trim($business['id']) === trim($businessId)) {
                $businessFound = true;
                
                // Pachtverträge hochladen, falls vorhanden
                if (isset($_FILES['contract_files']) && !empty($_FILES['contract_files']['name'][0])) {
                    $fileCount = count($_FILES['contract_files']['name']);
                    $filesUploaded = 0;
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['contract_files']['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $_FILES['contract_files']['tmp_name'][$i];
                            $fileName = $_FILES['contract_files']['name'][$i];
                            $fileSize = $_FILES['contract_files']['size'][$i];
                            
                            // MIME-Typ prüfen
                            $mimeInfo = checkFileMimeType($tmpName);
                            error_log("Datei: $fileName, MIME-Typ: " . $mimeInfo['mime'] . ", Erlaubt: " . ($mimeInfo['allowed'] ? 'Ja' : 'Nein'));
                            
                            if ($mimeInfo['allowed']) {
                                // Dateinamen bereinigen und eindeutig machen
                                $safeFileName = sanitizeFilename($fileName);
                                $uniqueFileName = uniqid() . '_' . $safeFileName;
                                $targetPath = $uploadsDir . $uniqueFileName;
                                
                                if (move_uploaded_file($tmpName, $targetPath)) {
                                    // Erfolgreich hochgeladen, zur Anlage hinzufügen
                                    $attachment = [
                                        'id' => generateUniqueId(),
                                        'file_name' => $uniqueFileName,
                                        'original_name' => $fileName,
                                        'mime_type' => $mimeInfo['mime'],
                                        'size' => $fileSize,
                                        'uploaded_at' => date('Y-m-d H:i:s'),
                                        'uploaded_by' => $user_id
                                    ];
                                    
                                    // Sicherstellen, dass das attachments-Array existiert
                                    if (!isset($businesses[$key]['attachments'])) {
                                        $businesses[$key]['attachments'] = [];
                                    }
                                    
                                    $businesses[$key]['attachments'][] = $attachment;
                                    $filesUploaded++;
                                    error_log("Datei erfolgreich hochgeladen: $targetPath");
                                } else {
                                    error_log("Fehler beim Verschieben der Datei nach: $targetPath");
                                    $error = 'Eine oder mehrere Anlagen konnten nicht hochgeladen werden.';
                                }
                            } else {
                                error_log("Ungültiger MIME-Typ: " . $mimeInfo['mime']);
                                $error = 'Eine oder mehrere Anlagen haben einen nicht unterstützten Dateityp (nur PDF, PNG, JPEG erlaubt).';
                            }
                        } else if ($_FILES['contract_files']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                            error_log("PHP Upload-Fehler: " . $_FILES['contract_files']['error'][$i]);
                            $error = 'Beim Hochladen einer oder mehrerer Anlagen ist ein Fehler aufgetreten.';
                        }
                    }
                    
                    // Wenn Dateien hochgeladen wurden, speichere die Änderungen
                    if ($filesUploaded > 0) {
                        // Metadaten aktualisieren
                        $businesses[$key]['updated_by'] = $user_id;
                        $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                        
                        if (saveJsonData($businessLicensesFile, $businesses)) {
                            logBusinessAction($business['id'], $business['name'], 'anhänge_hochgeladen', "$filesUploaded neue Pachtverträge hochgeladen");
                            $message = "$filesUploaded Pachtverträge erfolgreich hochgeladen.";
                        } else {
                            $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                        }
                    } else if (empty($error)) {
                        $error = 'Es wurden keine Dateien hochgeladen. Bitte wählen Sie mindestens eine Datei aus.';
                    }
                } else {
                    $error = 'Bitte wählen Sie mindestens eine Datei zum Hochladen aus.';
                }
                
                break;
            }
        }
        
        if (!$businessFound) {
            $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
        }
    }
    
    // Anhang löschen
    elseif ($action === 'delete_attachment' && isset($_POST['business_id']) && isset($_POST['attachment_id']) && currentUserCan('business_licenses', 'edit')) {
        $businessId = sanitize($_POST['business_id']);
        $attachmentId = sanitize($_POST['attachment_id']);
        
        $businessFound = false;
        $attachmentFound = false;
        
        foreach ($businesses as $key => $business) {
            if (trim($business['id']) === trim($businessId)) {
                $businessFound = true;
                
                // Suche im attachments-Array
                if (isset($business['attachments']) && is_array($business['attachments'])) {
                    foreach ($business['attachments'] as $attachmentKey => $attachment) {
                        if (isset($attachment['id']) && trim($attachment['id']) === trim($attachmentId)) {
                            $attachmentFound = true;
                            $fileName = $attachment['original_name'] ?? 'Unbekannte Datei';
                            
                            // Datei physisch löschen, falls vorhanden
                            if (isset($attachment['file_name'])) {
                                $filePath = $uploadsDir . $attachment['file_name'];
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                            }
                            
                            // Anhang aus dem Array entfernen
                            array_splice($businesses[$key]['attachments'], $attachmentKey, 1);
                            
                            // Metadaten aktualisieren
                            $businesses[$key]['updated_by'] = $user_id;
                            $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                            
                            if (saveJsonData($businessLicensesFile, $businesses)) {
                                // Protokolliere die Anhanglöschung
                                logBusinessAction($business['id'], $business['name'], 'anhang_gelöscht', "Anhang '$fileName' gelöscht");
                                $message = 'Anhang wurde erfolgreich gelöscht.';
                            } else {
                                $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                            }
                            break;
                        }
                    }
                }
                
                // Suche auch im alten "files"-Array für Kompatibilität
                if (!$attachmentFound && isset($business['files']) && is_array($business['files'])) {
                    foreach ($business['files'] as $fileKey => $file) {
                        if (isset($file['id']) && trim($file['id']) === trim($attachmentId)) {
                            $attachmentFound = true;
                            $fileName = $file['original_name'] ?? 'Unbekannte Datei';
                            
                            // Datei physisch löschen, falls vorhanden
                            if (isset($file['file_name'])) {
                                $filePath = $uploadsDir . $file['file_name'];
                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }
                            }
                            
                            // Anhang aus dem Array entfernen
                            array_splice($businesses[$key]['files'], $fileKey, 1);
                            
                            // Metadaten aktualisieren
                            $businesses[$key]['updated_by'] = $user_id;
                            $businesses[$key]['updated_at'] = date('Y-m-d H:i:s');
                            
                            if (saveJsonData($businessLicensesFile, $businesses)) {
                                // Protokolliere die Anhanglöschung
                                logBusinessAction($business['id'], $business['name'], 'anhang_gelöscht', "Anhang '$fileName' gelöscht");
                                $message = 'Anhang wurde erfolgreich gelöscht.';
                            } else {
                                $error = 'Fehler beim Speichern der aktualisierten Unternehmensdaten.';
                            }
                            break;
                        }
                    }
                }
                
                if (!$attachmentFound) {
                    $error = 'Der angegebene Anhang wurde nicht gefunden.';
                }
                break;
            }
        }
        
        if (!$businessFound) {
            $error = 'Das angegebene Unternehmen wurde nicht gefunden.';
        }
    }
    
    // Gewerbeschein generieren (vollständig neue Implementierung)
    elseif (isset($action) && $action === 'generate_certificate' && isset($_GET['business_id'])) {
        $businessId = sanitize($_GET['business_id']);
        $businessFound = false; // Setze das Flag auf false am Anfang
        
        // Daten erneut aus der Datei laden um Konsistenz zu gewährleisten
        $businesses = getJsonData($businessLicensesFile);
        if ($businesses === false) {
            error_log("Fehler beim Lesen der Datei: $businessLicensesFile");
            $businesses = []; 
        }
        
        // Finde das Unternehmen mit der verbesserten Helferfunktion
        $business = findBusinessById($businesses, $businessId);
        
        if ($business) {
            $businessFound = true;
            
            // Sicherstellen, dass die minimalen erforderlichen Felder vorhanden sind
            if (!isset($business['license_expiry'])) {
                error_log("Business " . $business['name'] . " hat kein license_expiry Datum");
                $business['license_expiry'] = date('Y-m-d');
            }
            
            if (!isset($business['foundation_date'])) {
                error_log("Business " . $business['name'] . " hat kein foundation_date");
                $business['foundation_date'] = date('Y-m-d');
            }
            
            // Aktualisiere den Status wenn nötig
            if (!isset($business['status']) || !is_array($business['status'])) {
                $status = calculateLicenseStatus($business['license_expiry']);
            } else {
                $status = $business['status'];
            }
            
            // Gewerbeschein generieren mit aktuellem Benutzernamen
            $certificateHtml = generateBusinessCertificate($business);
            
            // Protokolliere die Zertifikatsgenerierung
            logBusinessAction($business['id'], $business['name'], 'zertifikat_generiert', 
                             "Gewerbeschein generiert von $userFullName");
            
            // Prüfen, ob PDF-Download angefordert wurde
            if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
                // Als PDF generieren und zum Download anbieten
                $fileName = 'Gewerbeschein_' . sanitizeFilename($business['name']);
                generatePdfFromHtml($certificateHtml, $fileName, 'A4', 'portrait');
                // Die Funktion beendet die Ausführung automatisch
            } else {
                // HTML direkt ausgeben mit einem Link zum PDF-Download
                header('Content-Type: text/html; charset=utf-8');
                
                // PDF-Download-Link zum HTML hinzufügen
                $pdfUrl = 'business_licenses_new.php?action=generate_certificate&business_id=' . urlencode($business['id']) . '&format=pdf';
                
                // Button vor dem schließenden body-Tag einfügen
                $certificateHtml = str_replace('</body>', '
                <div style="text-align: center; margin-top: 30px;">
                    <a href="' . $pdfUrl . '" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                        PDF herunterladen
                    </a>
                </div>
                </body>', $certificateHtml);
                
                echo $certificateHtml;
                
                // Nach der Ausgabe des Zertifikats beenden wir das Skript
                exit;
            }
        }
        
        if (!$businessFound) {
            // Wenn das Unternehmen nicht gefunden wurde
            // Für AJAX-Anfragen: gib eine JSON-Fehlermeldung zurück
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Das angegebene Unternehmen wurde nicht gefunden.']);
            exit;
        }
    }
}

// Status aller Unternehmen aktualisieren
foreach ($businesses as $key => $business) {
    if (isset($business['license_expiry'])) {
        $businesses[$key]['status'] = calculateLicenseStatus($business['license_expiry']);
    }
}

// Filteroptionen für die Anzeige
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$searchQuery = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Gefilterte Liste von Unternehmen erstellen
$filteredBusinesses = $businesses;

// Nach Status filtern
if (!empty($statusFilter)) {
    $filteredBusinesses = array_filter($filteredBusinesses, function($business) use ($statusFilter) {
        return isset($business['status']['status']) && $business['status']['status'] === $statusFilter;
    });
}

// Nach Suchbegriff filtern
if (!empty($searchQuery)) {
    $filteredBusinesses = array_filter($filteredBusinesses, function($business) use ($searchQuery) {
        $searchLower = strtolower($searchQuery);
        
        // In verschiedenen Feldern suchen
        $nameMatch = isset($business['name']) && 
                     strpos(strtolower($business['name']), $searchLower) !== false;
        
        $ownerMatch = isset($business['owner_name']) && 
                      strpos(strtolower($business['owner_name']), $searchLower) !== false;
        
        $deputyMatch = isset($business['deputy_name']) && 
                       strpos(strtolower($business['deputy_name']), $searchLower) !== false;
        
        return $nameMatch || $ownerMatch || $deputyMatch;
    });
}

// Ab hier beginnt der HTML-Teil
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Gewerbeschein-Verwaltung</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if (currentUserCan('business_licenses', 'create')): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#addBusinessModal">
                        <i class="fas fa-plus"></i> Neues Unternehmen
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($message)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>
            
            <!-- Filter- und Suchfunktionen -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Filter und Suche</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status-Filter</label>
                            <select id="status" name="status" class="form-control">
                                <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>Alle Status</option>
                                <option value="green" <?php echo $statusFilter === 'green' ? 'selected' : ''; ?>>Gültig</option>
                                <option value="yellow" <?php echo $statusFilter === 'yellow' ? 'selected' : ''; ?>>Vorwarnung</option>
                                <option value="red" <?php echo $statusFilter === 'red' ? 'selected' : ''; ?>>Nachfrist</option>
                                <option value="black" <?php echo $statusFilter === 'black' ? 'selected' : ''; ?>>Letzter Aufschub</option>
                                <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Abgelaufen</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Suche</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                  placeholder="Suche nach Unternehmen, Inhaber, Stellvertreter..." 
                                  value="<?php echo htmlspecialchars($searchQuery); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filtern</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Übersicht aller Unternehmen -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Unternehmen mit Gewerbeschein</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Inhaber</th>
                                    <th>Stellvertreter</th>
                                    <th>Gründung</th>
                                    <th>Pachtbeginn</th>
                                    <th>Verlängert</th>
                                    <th>Ablaufdatum</th>
                                    <th>Status</th>
                                    <th>Benachrichtigt</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($filteredBusinesses)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-3">Keine Unternehmen gefunden.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($filteredBusinesses as $business): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($business['name'] ?? ''); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($business['owner_name'] ?? ''); ?>
                                        <?php if (!empty($business['owner_telegram'])): ?>
                                        <br><small class="text-muted">Telegram: <?php echo htmlspecialchars($business['owner_telegram']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($business['deputy_name'])): ?>
                                        <?php echo htmlspecialchars($business['deputy_name']); ?>
                                        <?php if (!empty($business['deputy_telegram'])): ?>
                                        <br><small class="text-muted">Telegram: <?php echo htmlspecialchars($business['deputy_telegram']); ?></small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo isset($business['foundation_date']) ? date('d.m.Y', strtotime($business['foundation_date'])) : '-'; ?></td>
                                    <td><?php echo isset($business['license_start_date']) ? date('d.m.Y', strtotime($business['license_start_date'])) : (isset($business['license_renewal_date']) ? date('d.m.Y', strtotime($business['license_renewal_date'])) : '-'); ?></td>
                                    <td><?php echo isset($business['license_renewal_date']) ? date('d.m.Y', strtotime($business['license_renewal_date'])) : '-'; ?></td>
                                    <td><?php echo isset($business['license_expiry']) ? date('d.m.Y', strtotime($business['license_expiry'])) : '-'; ?></td>
                                    <td>
                                        <?php 
                                        $status = $business['status'] ?? calculateLicenseStatus($business['license_expiry'] ?? date('Y-m-d'));
                                        $statusClass = $status['color_class'] ?? 'bg-secondary';
                                        $statusText = $status['text'] ?? 'Unbekannt';
                                        $daysRemaining = $status['days_remaining'] ?? 0;
                                        $needsAction = $status['needs_action'] ?? false;
                                        ?>
                                        
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        
                                        <?php if ($daysRemaining > 0): ?>
                                        <br><small><?php echo $daysRemaining; ?> Tage verbleibend</small>
                                        <?php elseif ($daysRemaining < 0): ?>
                                        <br><small><?php echo abs($daysRemaining); ?> Tage überfällig</small>
                                        <?php endif; ?>
                                        
                                        <?php if ($needsAction): ?>
                                        <br><span class="badge bg-danger">Handlungsbedarf</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $notifications = $business['notifications'] ?? [
                                            'pre_warning_sent' => false,
                                            'expiry_notice_sent' => false
                                        ];
                                        ?>
                                        
                                        <?php if ($notifications['pre_warning_sent']): ?>
                                        <span class="badge bg-warning text-dark">Vorwarnung ✓</span><br>
                                        <?php else: ?>
                                        <span class="badge bg-light text-dark">Vorwarnung ⨯</span><br>
                                        <?php endif; ?>
                                        
                                        <?php if ($notifications['expiry_notice_sent']): ?>
                                        <span class="badge bg-danger">Ablaufhinweis ✓</span>
                                        <?php else: ?>
                                        <span class="badge bg-light text-dark">Ablaufhinweis ⨯</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="btn-group">
                                            <!-- Gewerbeschein anzeigen -->
                                            <a href="business_licenses_new.php?action=generate_certificate&business_id=<?php echo $business['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Gewerbeschein anzeigen">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                            
                                            <!-- Gewerbeschein als PDF herunterladen -->
                                            <a href="business_licenses_new.php?action=generate_certificate&business_id=<?php echo $business['id']; ?>&format=pdf" 
                                               class="btn btn-sm btn-danger" title="Als PDF herunterladen">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                            
                                            <?php if (currentUserCan('business_licenses', 'edit')): ?>
                                            <!-- Unternehmen bearbeiten -->
                                            <button type="button" class="btn btn-sm btn-primary edit-business-btn" 
                                                   data-id="<?php echo htmlspecialchars($business['id']); ?>"
                                                   data-name="<?php echo htmlspecialchars($business['name'] ?? ''); ?>"
                                                   data-owner-name="<?php echo htmlspecialchars($business['owner_name'] ?? ''); ?>"
                                                   data-owner-telegram="<?php echo htmlspecialchars($business['owner_telegram'] ?? ''); ?>"
                                                   data-deputy-name="<?php echo htmlspecialchars($business['deputy_name'] ?? ''); ?>"
                                                   data-deputy-telegram="<?php echo htmlspecialchars($business['deputy_telegram'] ?? ''); ?>"
                                                   data-foundation-date="<?php echo htmlspecialchars($business['foundation_date'] ?? ''); ?>"
                                                   data-license-start-date="<?php echo htmlspecialchars($business['license_start_date'] ?? ''); ?>"
                                                   title="Unternehmen bearbeiten">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Gewerbeschein verlängern (One-Click) -->
                                            <form method="post" action="" class="d-inline" onsubmit="return confirm('Gewerbeschein für \"<?php echo htmlspecialchars($business['name'] ?? ''); ?>\" verlängern?');">
                                                <input type="hidden" name="action" value="renew_license">
                                                <input type="hidden" name="business_id" value="<?php echo htmlspecialchars($business['id']); ?>">
                                                <input type="hidden" name="license_start_date" value="<?php echo htmlspecialchars($business['license_start_date'] ?? date('Y-m-d')); ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="Gewerbeschein verlängern">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Mehr Optionen Dropdown -->
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" 
                                                       data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right">
                                                    <!-- Details anzeigen -->
                                                    <button type="button" class="dropdown-item view-details-btn" 
                                                           data-id="<?php echo htmlspecialchars($business['id']); ?>"
                                                           data-name="<?php echo htmlspecialchars($business['name'] ?? ''); ?>">
                                                        <i class="fas fa-info-circle mr-2"></i> Details anzeigen
                                                    </button>
                                                    
                                                    <!-- Lizenzen verwalten -->
                                                    <button type="button" class="dropdown-item manage-licenses-btn" 
                                                           data-id="<?php echo htmlspecialchars($business['id']); ?>"
                                                           data-name="<?php echo htmlspecialchars($business['name'] ?? ''); ?>">
                                                        <i class="fas fa-id-card mr-2"></i> Lizenzen verwalten
                                                    </button>
                                                    
                                                    <!-- Pachtverträge verwalten -->
                                                    <button type="button" class="dropdown-item manage-attachments-btn" 
                                                           data-id="<?php echo htmlspecialchars($business['id']); ?>"
                                                           data-name="<?php echo htmlspecialchars($business['name'] ?? ''); ?>">
                                                        <i class="fas fa-paperclip mr-2"></i> Pachtverträge verwalten
                                                    </button>
                                                    
                                                    <!-- Verlauf anzeigen -->
                                                    <button type="button" class="dropdown-item view-history-btn" 
                                                           data-id="<?php echo htmlspecialchars($business['id']); ?>"
                                                           data-name="<?php echo htmlspecialchars($business['name'] ?? ''); ?>">
                                                        <i class="fas fa-history mr-2"></i> Verlauf anzeigen
                                                    </button>
                                                    
                                                    <div class="dropdown-divider"></div>
                                                    
                                                    <!-- Benachrichtigungen markieren -->
                                                    <h6 class="dropdown-header">Benachrichtigungen</h6>
                                                    <form method="post" action="" class="px-4 py-1">
                                                        <input type="hidden" name="action" value="mark_notification">
                                                        <input type="hidden" name="business_id" value="<?php echo htmlspecialchars($business['id']); ?>">
                                                        <input type="hidden" name="notification_type" value="pre_warning">
                                                        <button type="submit" class="btn btn-sm <?php echo $notifications['pre_warning_sent'] ? 'btn-success' : 'btn-outline-warning'; ?> w-100 mb-2">
                                                            <?php echo $notifications['pre_warning_sent'] ? '<i class="fas fa-check-circle mr-1"></i>' : '<i class="fas fa-bell mr-1"></i>'; ?> 
                                                            Vorwarnung
                                                        </button>
                                                    </form>
                                                    <form method="post" action="" class="px-4 py-1">
                                                        <input type="hidden" name="action" value="mark_notification">
                                                        <input type="hidden" name="business_id" value="<?php echo htmlspecialchars($business['id']); ?>">
                                                        <input type="hidden" name="notification_type" value="expiry_notice">
                                                        <button type="submit" class="btn btn-sm <?php echo $notifications['expiry_notice_sent'] ? 'btn-success' : 'btn-outline-danger'; ?> w-100">
                                                            <?php echo $notifications['expiry_notice_sent'] ? '<i class="fas fa-check-circle mr-1"></i>' : '<i class="fas fa-exclamation-circle mr-1"></i>'; ?> 
                                                            Ablaufhinweis
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if (currentUserCan('business_licenses', 'delete') || checkUserHasRoleType(['chief_justice', 'senior_associate_justice'])): ?>
                                                    <div class="dropdown-divider"></div>
                                                    
                                                    <!-- Unternehmen löschen -->
                                                    <form method="post" action="" class="delete-business-form"
                                                         onsubmit="return confirm('Sind Sie sicher, dass Sie das Unternehmen \"<?php echo htmlspecialchars($business['name'] ?? ''); ?>\" löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                                        <input type="hidden" name="action" value="delete_business">
                                                        <input type="hidden" name="business_id" value="<?php echo htmlspecialchars($business['id']); ?>">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash-alt mr-2"></i> Unternehmen löschen
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
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

<!-- Modal: Neues Unternehmen hinzufügen -->
<div class="modal fade" id="addBusinessModal" tabindex="-1" role="dialog" aria-labelledby="addBusinessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addBusinessModalLabel">Neues Unternehmen hinzufügen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="" enctype="multipart/form-data" id="addBusinessForm">
                    <input type="hidden" name="action" value="add_business">
                    
                    <div class="form-group">
                        <label for="business_name">Name des Unternehmens *</label>
                        <input type="text" class="form-control" id="business_name" name="business_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="owner_name">Name des Geschäftsführers *</label>
                                <input type="text" class="form-control" id="owner_name" name="owner_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="owner_telegram">Telegram-Nummer des Geschäftsführers *</label>
                                <input type="text" class="form-control" id="owner_telegram" name="owner_telegram" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deputy_name">Name des Stellvertreters</label>
                                <input type="text" class="form-control" id="deputy_name" name="deputy_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deputy_telegram">Telegram-Nummer des Stellvertreters</label>
                                <input type="text" class="form-control" id="deputy_telegram" name="deputy_telegram">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="foundation_date">Gründungsdatum</label>
                        <input type="date" class="form-control" id="foundation_date" name="foundation_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="license_start_date">Startdatum des Pachtvertrags *</label>
                        <input type="date" class="form-control" id="license_start_date" name="license_start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        <small class="form-text text-muted">Ab diesem Datum werden die 28 Tage Gültigkeit berechnet.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="contract_files">Pachtverträge hochladen (PDF, PNG, JPEG, max. 10MB)</label>
                        <input type="file" class="form-control-file" id="contract_files" name="contract_files[]" multiple accept=".pdf,.png,.jpg,.jpeg">
                        <small class="form-text text-muted">Sie können mehrere Dateien auswählen.</small>
                    </div>
                    
                    <div class="text-right mt-4">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Unternehmen hinzufügen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Unternehmen bearbeiten -->
<div class="modal fade" id="editBusinessModal" tabindex="-1" role="dialog" aria-labelledby="editBusinessModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editBusinessModalLabel">Unternehmen bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="" enctype="multipart/form-data" id="editBusinessForm">
                    <input type="hidden" name="action" value="update_business">
                    <input type="hidden" name="business_id" id="edit_business_id">
                    
                    <div class="form-group">
                        <label for="edit_business_name">Name des Unternehmens *</label>
                        <input type="text" class="form-control" id="edit_business_name" name="business_name" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_owner_name">Name des Geschäftsführers *</label>
                                <input type="text" class="form-control" id="edit_owner_name" name="owner_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_owner_telegram">Telegram-Nummer des Geschäftsführers *</label>
                                <input type="text" class="form-control" id="edit_owner_telegram" name="owner_telegram" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_deputy_name">Name des Stellvertreters</label>
                                <input type="text" class="form-control" id="edit_deputy_name" name="deputy_name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="edit_deputy_telegram">Telegram-Nummer des Stellvertreters</label>
                                <input type="text" class="form-control" id="edit_deputy_telegram" name="deputy_telegram">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_foundation_date">Gründungsdatum</label>
                        <input type="date" class="form-control" id="edit_foundation_date" name="foundation_date">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_license_start_date">Startdatum des Pachtvertrags *</label>
                        <input type="date" class="form-control" id="edit_license_start_date" name="license_start_date" required>
                        <small class="form-text text-muted">Ab diesem Datum werden die 28 Tage Gültigkeit berechnet.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_contract_files">Neue Pachtverträge hochladen (PDF, PNG, JPEG, max. 10MB)</label>
                        <input type="file" class="form-control-file" id="edit_contract_files" name="contract_files[]" multiple accept=".pdf,.png,.jpg,.jpeg">
                        <small class="form-text text-muted">Sie können mehrere Dateien auswählen. Bestehende Dateien können über "Pachtverträge verwalten" eingesehen werden.</small>
                    </div>
                    
                    <div class="text-right mt-4">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary">Änderungen speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Lizenzen verwalten -->
<div class="modal fade" id="manageLicensesModal" tabindex="-1" role="dialog" aria-labelledby="manageLicensesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageLicensesModalLabel">Lizenzen verwalten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="licenses-container">
                    <p>Lizenzen werden geladen...</p>
                </div>
                
                <hr>
                
                <h5>Neue Lizenz hinzufügen</h5>
                <form method="post" action="" id="addLicenseForm">
                    <input type="hidden" name="action" value="add_license">
                    <input type="hidden" name="business_id" id="license_business_id">
                    
                    <div class="form-group">
                        <label for="license_name">Bezeichnung der Lizenz *</label>
                        <input type="text" class="form-control" id="license_name" name="license_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="license_description">Beschreibung</label>
                        <textarea class="form-control" id="license_description" name="license_description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="license_expiry">Ablaufdatum</label>
                        <input type="date" class="form-control" id="license_expiry" name="license_expiry" value="<?php echo date('Y-m-d', strtotime('+28 days')); ?>">
                        <small class="form-text text-muted">Wenn kein Datum angegeben wird, läuft die Lizenz in 28 Tagen ab.</small>
                    </div>
                    
                    <div class="text-right mt-3">
                        <button type="submit" class="btn btn-primary">Lizenz hinzufügen</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Anhänge verwalten -->
<div class="modal fade" id="manageAttachmentsModal" tabindex="-1" role="dialog" aria-labelledby="manageAttachmentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageAttachmentsModalLabel">Pachtverträge verwalten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="attachments-container">
                    <p>Pachtverträge werden geladen...</p>
                </div>
                
                <hr>
                
                <h5>Neuen Pachtvertrag hochladen</h5>
                <form method="post" action="" enctype="multipart/form-data" id="addAttachmentForm">
                    <input type="hidden" name="action" value="upload_attachment">
                    <input type="hidden" name="business_id" id="attachment_business_id">
                    
                    <div class="form-group">
                        <label for="attachment_files">Pachtverträge hochladen (PDF, PNG, JPEG, max. 10MB)</label>
                        <input type="file" class="form-control-file" id="attachment_files" name="contract_files[]" multiple accept=".pdf,.png,.jpg,.jpeg" required>
                        <small class="form-text text-muted">Sie können mehrere Dateien auswählen.</small>
                    </div>
                    
                    <div class="text-right mt-3">
                        <button type="submit" class="btn btn-primary">Hochladen</button>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Verlauf anzeigen -->
<div class="modal fade" id="viewHistoryModal" tabindex="-1" role="dialog" aria-labelledby="viewHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewHistoryModalLabel">Verlauf</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                <div id="history-container">
                    <p>Verlauf wird geladen...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Anhang-Vorschau -->
<div class="modal fade" id="attachmentPreviewModal" tabindex="-1" role="dialog" aria-labelledby="attachmentPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attachmentPreviewModalLabel">Datei-Vorschau</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center p-0">
                <div id="attachment-preview-container" class="p-2">
                    <p>Vorschau wird geladen...</p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="attachment-download-link" class="btn btn-primary" download>Herunterladen</a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Unternehmensdetails -->
<div class="modal fade" id="businessDetailsModal" tabindex="-1" role="dialog" aria-labelledby="businessDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="businessDetailsModalLabel">Unternehmensdetails</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="business-details-container">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Wird geladen...</span>
                        </div>
                        <p class="mt-2">Details werden geladen...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript-Funktionen für die Gewerbeschein-Verwaltung

/**
 * Initialisierung beim Laden der Seite
 */
document.addEventListener('DOMContentLoaded', function() {
    // Event-Listener für die Bearbeiten-Buttons
    document.querySelectorAll('.edit-business-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            // Formulardaten aus den data-Attributen füllen
            document.getElementById('edit_business_id').value = this.getAttribute('data-id');
            document.getElementById('edit_business_name').value = this.getAttribute('data-name');
            document.getElementById('edit_owner_name').value = this.getAttribute('data-owner-name');
            document.getElementById('edit_owner_telegram').value = this.getAttribute('data-owner-telegram');
            document.getElementById('edit_deputy_name').value = this.getAttribute('data-deputy-name') || '';
            document.getElementById('edit_deputy_telegram').value = this.getAttribute('data-deputy-telegram') || '';
            document.getElementById('edit_foundation_date').value = this.getAttribute('data-foundation-date') || '';
            document.getElementById('edit_license_start_date').value = this.getAttribute('data-license-start-date') || '';
            
            // Modal öffnen
            $('#editBusinessModal').modal('show');
        });
    });
    
    // Event-Listener für die Lizenzen-Verwaltung-Buttons
    document.querySelectorAll('.manage-licenses-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const businessId = this.getAttribute('data-id');
            const businessName = this.getAttribute('data-name');
            
            // Business-ID im Formular setzen
            document.getElementById('license_business_id').value = businessId;
            
            // Modal-Titel anpassen
            document.getElementById('manageLicensesModalLabel').textContent = 'Lizenzen verwalten: ' + businessName;
            
            // Lizenzen laden
            loadLicenses(businessId);
            
            // Modal öffnen
            $('#manageLicensesModal').modal('show');
        });
    });
    
    // Event-Listener für die Anhänge-Verwaltung-Buttons
    document.querySelectorAll('.manage-attachments-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const businessId = this.getAttribute('data-id');
            const businessName = this.getAttribute('data-name');
            
            // Business-ID im Formular setzen
            document.getElementById('attachment_business_id').value = businessId;
            
            // Modal-Titel anpassen
            document.getElementById('manageAttachmentsModalLabel').textContent = 'Pachtverträge verwalten: ' + businessName;
            
            // Anhänge laden
            loadAttachments(businessId);
            
            // Modal öffnen
            $('#manageAttachmentsModal').modal('show');
        });
    });
    
    // Event-Listener für die Detailansicht-Buttons
    document.querySelectorAll('.view-details-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const businessId = this.getAttribute('data-id');
            const businessName = this.getAttribute('data-name');
            
            // Modal-Titel anpassen
            document.getElementById('businessDetailsModalLabel').textContent = 'Details: ' + businessName;
            
            // Details laden
            loadBusinessDetails(businessId);
            
            // Modal öffnen
            $('#businessDetailsModal').modal('show');
        });
    });

    // Event-Listener für die Verlauf-Anzeige-Buttons
    document.querySelectorAll('.view-history-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const businessId = this.getAttribute('data-id');
            const businessName = this.getAttribute('data-name');
            
            // Modal-Titel anpassen
            document.getElementById('viewHistoryModalLabel').textContent = 'Verlauf: ' + businessName;
            
            // Verlauf laden
            loadHistory(businessId);
            
            // Modal öffnen
            $('#viewHistoryModal').modal('show');
        });
    });
});

/**
 * Lädt die Lizenzen eines Unternehmens per AJAX
 */
function loadLicenses(businessId) {
    const container = document.getElementById('licenses-container');
    container.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Lizenzen werden geladen...</p>';
    
    // Hier würde normalerweise ein AJAX-Request stehen
    // Da wir aber die Daten bereits auf der Seite haben, simulieren wir das
    
    // Suche das Unternehmen mit der passenden ID
    const businesses = <?php echo json_encode($businesses); ?>;
    let business = null;
    
    for (const b of businesses) {
        if (b.id === businessId) {
            business = b;
            break;
        }
    }
    
    if (!business || !business.licenses || business.licenses.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Diesem Unternehmen sind noch keine Lizenzen zugeordnet.</div>';
        return;
    }
    
    // Lizenzen anzeigen
    let html = '<div class="list-group">';
    
    for (const license of business.licenses) {
        const status = license.status || calculateLicenseStatusJS(license.expiry_date);
        const statusClass = status.color_class || 'bg-secondary';
        const statusText = status.text || 'Unbekannt';
        
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-1">${escapeHtml(license.name || 'Unbekannte Lizenz')}</h5>
                    ${license.description ? `<p class="mb-1 text-muted">${escapeHtml(license.description)}</p>` : ''}
                    <small>
                        Ausgestellt: ${license.issue_date ? formatDate(license.issue_date) : 'Unbekannt'} | 
                        Gültig bis: ${license.expiry_date ? formatDate(license.expiry_date) : 'Unbegrenzt'}
                    </small>
                    <br>
                    <span class="badge ${statusClass}">${statusText}</span>
                </div>
                <form method="post" action="" onsubmit="return confirm('Sind Sie sicher, dass Sie diese Lizenz löschen möchten?');">
                    <input type="hidden" name="action" value="delete_license">
                    <input type="hidden" name="business_id" value="${businessId}">
                    <input type="hidden" name="license_id" value="${license.id}">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
            </div>
        `;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Lädt die Anhänge eines Unternehmens per AJAX
 */
function loadAttachments(businessId) {
    const container = document.getElementById('attachments-container');
    container.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Pachtverträge werden geladen...</p>';
    
    // Hier würde normalerweise ein AJAX-Request stehen
    // Da wir aber die Daten bereits auf der Seite haben, simulieren wir das
    
    // Suche das Unternehmen mit der passenden ID
    const businesses = <?php echo json_encode($businesses); ?>;
    let business = null;
    
    for (const b of businesses) {
        if (b.id === businessId) {
            business = b;
            break;
        }
    }
    
    // Attachments und files zusammenführen für Kompatibilität
    let attachments = [];
    if (business) {
        if (business.attachments && business.attachments.length > 0) {
            attachments = attachments.concat(business.attachments);
        }
        if (business.files && business.files.length > 0) {
            attachments = attachments.concat(business.files);
        }
    }
    
    if (!attachments || attachments.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Diesem Unternehmen sind noch keine Pachtverträge zugeordnet.</div>';
        return;
    }
    
    // Anhänge anzeigen
    let html = '<div class="row">';
    
    for (const attachment of attachments) {
        const fileName = attachment.original_name || attachment.file_name || 'Unbekannte Datei';
        const fileSize = formatFileSize(attachment.size || 0);
        const uploadDate = attachment.uploaded_at ? formatDate(attachment.uploaded_at) : 'Unbekannt';
        const fileType = attachment.mime_type || '';
        const filePath = `../uploads/business_licenses/${attachment.file_name}`;
        
        // Icon basierend auf Dateityp
        let iconClass = 'fas fa-file';
        if (fileType.includes('pdf')) {
            iconClass = 'fas fa-file-pdf';
        } else if (fileType.includes('image')) {
            iconClass = 'fas fa-file-image';
        }
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="${iconClass} mr-2"></i>
                            ${escapeHtml(fileName)}
                        </h5>
                        <p class="card-text">
                            <small class="text-muted">
                                Größe: ${fileSize}<br>
                                Hochgeladen: ${uploadDate}
                            </small>
                        </p>
                    </div>
                    <div class="card-footer d-flex justify-content-between bg-transparent">
                        <button type="button" class="btn btn-sm btn-info preview-attachment-btn" 
                               data-file="${filePath}" 
                               data-name="${escapeHtml(fileName)}"
                               data-type="${fileType}">
                            <i class="fas fa-eye mr-1"></i> Vorschau
                        </button>
                        <form method="post" action="" onsubmit="return confirm('Sind Sie sicher, dass Sie diesen Anhang löschen möchten?');">
                            <input type="hidden" name="action" value="delete_attachment">
                            <input type="hidden" name="business_id" value="${businessId}">
                            <input type="hidden" name="attachment_id" value="${attachment.id}">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash-alt mr-1"></i> Löschen
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    container.innerHTML = html;
    
    // Event-Listener für Vorschau-Buttons hinzufügen
    document.querySelectorAll('.preview-attachment-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            const filePath = this.getAttribute('data-file');
            const fileName = this.getAttribute('data-name');
            const fileType = this.getAttribute('data-type');
            
            // Modal-Titel anpassen
            document.getElementById('attachmentPreviewModalLabel').textContent = 'Vorschau: ' + fileName;
            
            // Download-Link setzen
            const downloadLink = document.getElementById('attachment-download-link');
            downloadLink.href = filePath;
            downloadLink.setAttribute('download', fileName);
            
            // Vorschau-Container leeren
            const previewContainer = document.getElementById('attachment-preview-container');
            previewContainer.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Vorschau wird geladen...</p>';
            
            // Vorschau basierend auf Dateityp generieren
            if (fileType.includes('image')) {
                previewContainer.innerHTML = `<img src="${filePath}" class="img-fluid" alt="${fileName}">`;
            } else if (fileType.includes('pdf')) {
                previewContainer.innerHTML = `
                    <div style="height: 80vh;">
                        <object data="${filePath}" type="application/pdf" width="100%" height="100%">
                            <p>Ihr Browser unterstützt keine PDF-Vorschau. <a href="${filePath}" target="_blank">PDF herunterladen</a></p>
                        </object>
                    </div>
                `;
            } else {
                previewContainer.innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>
                        Für diesen Dateityp ist keine Vorschau verfügbar.
                        <br><br>
                        <a href="${filePath}" class="btn btn-primary" target="_blank">Datei öffnen</a>
                    </div>
                `;
            }
            
            // Modal öffnen
            $('#attachmentPreviewModal').modal('show');
        });
    });
}

/**
 * Lädt den Verlauf eines Unternehmens per AJAX
 */
function loadHistory(businessId) {
    const container = document.getElementById('history-container');
    container.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Verlauf wird geladen...</p>';
    
    // Hier würde normalerweise ein AJAX-Request stehen
    // Da wir aber die Daten bereits auf der Seite haben, simulieren wir das
    
    // Such alle Verlaufseinträge für das Unternehmen
    const logEntries = <?php echo json_encode($logEntries); ?>;
    const filteredEntries = logEntries.filter(entry => entry.business_id === businessId);
    
    if (filteredEntries.length === 0) {
        container.innerHTML = '<div class="alert alert-info">Für dieses Unternehmen sind noch keine Aktionen protokolliert.</div>';
        return;
    }
    
    // Verlaufseinträge anzeigen
    let html = '<div class="timeline">';
    
    for (const entry of filteredEntries) {
        const timestamp = entry.timestamp ? formatDateTime(entry.timestamp) : 'Unbekannt';
        const username = entry.username || 'Unbekannter Benutzer';
        const action = entry.action || 'Unbekannte Aktion';
        const details = entry.details || '';
        
        // Icon und Farbe basierend auf Aktion
        let iconClass = 'fas fa-info-circle';
        let badgeClass = 'badge-info';
        
        if (action.includes('erstellt')) {
            iconClass = 'fas fa-plus-circle';
            badgeClass = 'badge-success';
        } else if (action.includes('bearbeitet')) {
            iconClass = 'fas fa-edit';
            badgeClass = 'badge-primary';
        } else if (action.includes('gelöscht')) {
            iconClass = 'fas fa-trash-alt';
            badgeClass = 'badge-danger';
        } else if (action.includes('verlängert')) {
            iconClass = 'fas fa-sync-alt';
            badgeClass = 'badge-success';
        } else if (action.includes('benachrichtigt')) {
            iconClass = 'fas fa-bell';
            badgeClass = 'badge-warning';
        } else if (action.includes('lizenz')) {
            iconClass = 'fas fa-id-card';
            badgeClass = 'badge-info';
        } else if (action.includes('anhang')) {
            iconClass = 'fas fa-paperclip';
            badgeClass = 'badge-secondary';
        } else if (action.includes('zertifikat')) {
            iconClass = 'fas fa-certificate';
            badgeClass = 'badge-primary';
        }
        
        html += `
            <div class="timeline-item">
                <div class="timeline-item-content">
                    <span class="badge ${badgeClass}">
                        <i class="${iconClass} mr-1"></i> ${action}
                    </span>
                    <time>${timestamp}</time>
                    <p>${details}</p>
                    <span class="text-muted">Durchgeführt von: ${username}</span>
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Berechnet den Status einer Lizenz basierend auf dem Ablaufdatum (JavaScript-Version)
 */
function calculateLicenseStatusJS(expiryDate) {
    // Aktuelles Datum
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    try {
        // Ablaufdatum
        const expiry = new Date(expiryDate);
        expiry.setHours(0, 0, 0, 0);
        
        // Differenz in Tagen
        const differenceTime = expiry.getTime() - today.getTime();
        const daysRemaining = Math.ceil(differenceTime / (1000 * 3600 * 24));
        
        // Normale Gültigkeit (grün): mehr als 7 Tage verbleibend
        if (daysRemaining > 7) {
            return {
                status: 'green',
                days_remaining: daysRemaining,
                text: 'Gültig',
                color_class: 'bg-success',
                needs_action: false
            };
        } 
        // Vorwarnphase (gelb): 7 oder weniger Tage verbleibend
        else if (daysRemaining >= 0) {
            return {
                status: 'yellow',
                days_remaining: daysRemaining,
                text: 'Vorwarnung',
                color_class: 'bg-warning',
                needs_action: true
            };
        } 
        // Nachfrist (rot): Bis zu 28 Tage nach Ablauf
        else if (daysRemaining >= -28) {
            return {
                status: 'red',
                days_remaining: daysRemaining,
                text: 'Nachfrist',
                color_class: 'bg-danger',
                needs_action: true
            };
        } 
        // Letzter Aufschub (schwarz): Zwischen 29 und 42 Tagen nach Ablauf
        else if (daysRemaining >= -42) {
            return {
                status: 'black',
                days_remaining: daysRemaining,
                text: 'Letzter Aufschub',
                color_class: 'bg-dark',
                needs_action: true
            };
        } 
        // Abgelaufen (grau): Mehr als 42 Tage nach Ablauf
        else {
            return {
                status: 'expired',
                days_remaining: daysRemaining,
                text: 'Abgelaufen',
                color_class: 'bg-secondary',
                needs_action: true
            };
        }
    } catch (e) {
        console.error("Fehler bei der Statusberechnung:", e);
        
        // Fallback bei ungültigem Datum
        return {
            status: 'unknown',
            days_remaining: 0,
            text: 'Unbekannt',
            color_class: 'bg-secondary',
            needs_action: true
        };
    }
}

/**
 * Formatiert ein Datum in deutsches Format
 */
function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('de-DE');
    } catch (e) {
        return dateString || 'Unbekannt';
    }
}

/**
 * Formatiert ein Datum mit Uhrzeit in deutsches Format
 */
function formatDateTime(dateTimeString) {
    try {
        const date = new Date(dateTimeString);
        return date.toLocaleDateString('de-DE') + ' ' + 
               date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return dateTimeString || 'Unbekannt';
    }
}

/**
 * Formatiert eine Dateigröße in besser lesbares Format
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Lädt die Detailansicht eines Unternehmens
 */
function loadBusinessDetails(businessId) {
    const container = document.getElementById('business-details-container');
    container.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="sr-only">Wird geladen...</span></div><p class="mt-2">Details werden geladen...</p></div>';
    
    // Da wir die Daten bereits auf der Seite haben, suchen wir das Unternehmen
    const businesses = <?php echo json_encode($businesses); ?>;
    let business = null;
    
    for (const b of businesses) {
        if (b.id === businessId) {
            business = b;
            break;
        }
    }
    
    if (!business) {
        container.innerHTML = '<div class="alert alert-danger">Unternehmen nicht gefunden.</div>';
        return;
    }
    
    // Formatiere die Daten für die Anzeige
    const foundationDate = business.foundation_date ? new Date(business.foundation_date).toLocaleDateString('de-DE') : 'Unbekannt';
    const startDate = business.license_start_date ? new Date(business.license_start_date).toLocaleDateString('de-DE') : 'Unbekannt';
    const renewalDate = business.license_renewal_date ? new Date(business.license_renewal_date).toLocaleDateString('de-DE') : 'Unbekannt';
    const expiryDate = business.license_expiry ? new Date(business.license_expiry).toLocaleDateString('de-DE') : 'Unbekannt';
    
    // Status für die Anzeige vorbereiten
    let statusBadge = '';
    if (business.status) {
        statusBadge = `<span class="badge ${business.status.color_class}">${business.status.text}</span>`;
    } else {
        statusBadge = '<span class="badge bg-secondary">Unbekannt</span>';
    }
    
    // Erstelle die Detailansicht
    let html = `
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Unternehmensinformationen</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Basisinformationen</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Name:</th>
                                <td>${escapeHtml(business.name || '')}</td>
                            </tr>
                            <tr>
                                <th>Gründungsdatum:</th>
                                <td>${foundationDate}</td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>${statusBadge}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Gewerbeschein</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Startdatum Pachtvertrag:</th>
                                <td>${startDate}</td>
                            </tr>
                            <tr>
                                <th>Letzte Verlängerung:</th>
                                <td>${renewalDate}</td>
                            </tr>
                            <tr>
                                <th>Gültig bis:</th>
                                <td>${expiryDate}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Inhaber</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Name:</th>
                                <td>${escapeHtml(business.owner_name || '')}</td>
                            </tr>
                            <tr>
                                <th>Telegram:</th>
                                <td>${escapeHtml(business.owner_telegram || 'Nicht angegeben')}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Stellvertreter</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Name:</th>
                                <td>${escapeHtml(business.deputy_name || 'Nicht angegeben')}</td>
                            </tr>
                            <tr>
                                <th>Telegram:</th>
                                <td>${escapeHtml(business.deputy_telegram || 'Nicht angegeben')}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-12">
                        <h6>Schnellzugriff</h6>
                        <div class="btn-group">
                            <a href="business_licenses_new.php?action=generate_certificate&business_id=${business.id}" class="btn btn-warning mr-1" target="_blank">
                                <i class="fas fa-file-certificate mr-1"></i> Gewerbeschein anzeigen
                            </a>
                            <a href="business_licenses_new.php?action=generate_certificate&business_id=${business.id}&format=pdf" class="btn btn-danger" target="_blank">
                                <i class="fas fa-file-pdf mr-1"></i> Als PDF herunterladen
                            </a>
                            <button type="button" class="btn btn-primary view-attachments-from-details" data-id="${business.id}">
                                <i class="fas fa-paperclip mr-1"></i> Pachtverträge
                            </button>
                            <button type="button" class="btn btn-info view-licenses-from-details" data-id="${business.id}">
                                <i class="fas fa-id-card mr-1"></i> Lizenzen
                            </button>
                            <button type="button" class="btn btn-secondary view-history-from-details" data-id="${business.id}">
                                <i class="fas fa-history mr-1"></i> Verlauf
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
    
    // Event-Listener für die Schnellzugriff-Buttons
    document.querySelector('.view-attachments-from-details').addEventListener('click', function() {
        $('#businessDetailsModal').modal('hide');
        const id = this.getAttribute('data-id');
        document.querySelector(`.manage-attachments-btn[data-id="${id}"]`).click();
    });
    
    document.querySelector('.view-licenses-from-details').addEventListener('click', function() {
        $('#businessDetailsModal').modal('hide');
        const id = this.getAttribute('data-id');
        document.querySelector(`.manage-licenses-btn[data-id="${id}"]`).click();
    });
    
    document.querySelector('.view-history-from-details').addEventListener('click', function() {
        $('#businessDetailsModal').modal('hide');
        const id = this.getAttribute('data-id');
        document.querySelector(`.view-history-btn[data-id="${id}"]`).click();
    });
}

/**
 * Hilfsfunktion zum Escapen von HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Stilregeln für die Timeline
document.head.insertAdjacentHTML('beforeend', `
    <style>
    .timeline {
        position: relative;
        max-width: 100%;
        margin: 0 auto;
    }
    
    .timeline::after {
        content: '';
        position: absolute;
        width: 2px;
        background-color: #e9ecef;
        top: 0;
        bottom: 0;
        left: 22px;
        margin-left: -1px;
    }
    
    .timeline-item {
        padding: 10px 40px;
        position: relative;
    }
    
    .timeline-item::after {
        content: '';
        position: absolute;
        width: 16px;
        height: 16px;
        background-color: white;
        border: 2px solid #6c757d;
        border-radius: 50%;
        z-index: 1;
        left: 22px;
        top: 15px;
        transform: translateX(-50%);
    }
    
    .timeline-item-content {
        padding: 15px;
        background-color: white;
        border-radius: 5px;
        border: 1px solid #e9ecef;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
    }
    
    .timeline-item-content time {
        display: block;
        font-size: 0.85rem;
        color: #6c757d;
        margin: 8px 0;
    }
    </style>
`);
</script>

<?php
include_once '../includes/footer.php';
?>