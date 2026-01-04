<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Fallbearbeitungsmodul
 * 
 * Dieses Modul ermöglicht die Bearbeitung von Falldaten und Klageschriften.
 */

session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Überprüfe, ob der Benutzer angemeldet ist
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];
$message = '';
$error = '';

// Verwende die checkUserHasRoleType Funktion für konsistente Rollenüberprüfung
$userRole = $_SESSION['role'];
$isProsecutor = checkUserHasRoleType($userRole, 'prosecutor');
$isLeadership = checkUserHasRoleType($userRole, 'leadership');
$isJudge = checkUserHasRoleType($userRole, 'judge');
$isMarshal = checkUserHasRoleType($userRole, 'marshal');

// Debug-Info
error_log("Rollenprüfung: " . $_SESSION['role'] . 
      ", isProsecutor: " . ($isProsecutor ? "true" : "false") . 
      ", isLeadership: " . ($isLeadership ? "true" : "false") .
      ", isJudge: " . ($isJudge ? "true" : "false") .
      ", isMarshal: " . ($isMarshal ? "true" : "false"));

// Id des zu bearbeitenden Falls aus der URL erhalten
$caseId = isset($_GET['id']) ? $_GET['id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;

// Überprüfe, ob die ID gültig ist
if (!$caseId) {
    header('Location: cases.php');
    exit;
}

// Fall aus der Datenbank laden
$caseData = findById('cases.json', $caseId);

// Überprüfen, ob der Fall existiert
if (!$caseData) {
    $error = 'Fall nicht gefunden.';
    header('Location: cases.php');
    exit;
}

// Verarbeite GET parameter 'action' für direkte Aktionen
if ($action === 'close_case') {
    // Verwende die zentrale Rollenprüfung (checkUserHasRoleType)
    $userRole = $_SESSION['role'];
    $isProsecutor = checkUserHasRoleType($userRole, 'prosecutor');
    $isLeadership = checkUserHasRoleType($userRole, 'leadership');
    
    // Debug-Info
    error_log("Zugriffsprüfung für Fall schließen - Rolle: " . $_SESSION['role'] . 
              ", isProsecutor: " . ($isProsecutor ? "true" : "false") . 
              ", isLeadership: " . ($isLeadership ? "true" : "false"));
    
    if ($isProsecutor || $isLeadership) {
        // Zeige ein Formular an, um den Fall zu schließen
        $showCloseForm = true;
    } else {
        // Keine Berechtigung - zurück zur Fallübersicht
        header("Location: ../access_denied.php");
        exit;
    }
}

// Überprüfe die Verjährung des Falls und aktualisiere, falls nötig
$caseData = checkCaseExpiration($caseData);

// Finden der vorhandenen Klageschrift für diesen Fall
$existingIndictment = null;
$indictments = getJsonData('indictments.json');
foreach ($indictments as $indictment) {
    if ($indictment['case_id'] === $caseId) {
        $existingIndictment = $indictment;
        break;
    }
}

// Verarbeitung von Formularübermittlungen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fallaktualisierung (grundlegende Informationen)
    if (isset($_POST['action']) && $_POST['action'] === 'update_case') {
        $defendant = sanitize($_POST['defendant'] ?? '');
        $charge = sanitize($_POST['charge'] ?? '');
        $incidentDate = sanitize($_POST['incident_date'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        $prosecutorName = sanitize($_POST['prosecutor'] ?? '');
        $judgeName = sanitize($_POST['judge'] ?? '');
        $newNote = sanitize($_POST['new_note'] ?? '');
        $bailAmount = sanitize($_POST['bail_amount'] ?? '');
        $witnesses = sanitize($_POST['witnesses'] ?? '');
        $victims = sanitize($_POST['victims'] ?? '');
        
        // Verjährungsdatum berechnen (standardmäßig 21 Tage nach Vorfalldatum)
        $expirationDays = 21;
        $expirationDate = date('Y-m-d', strtotime($incidentDate . " +{$expirationDays} days"));
        
        // Validierung
        if (empty($defendant) || empty($charge) || empty($incidentDate) || empty($district)) {
            $error = 'Bitte füllen Sie alle Pflichtfelder aus.';
        } else {
            // Fall aktualisieren
            $updatedCase = $caseData;
            $updatedCase['defendant'] = $defendant;
            $updatedCase['charge'] = $charge;
            $updatedCase['incident_date'] = $incidentDate;
            $updatedCase['expiration_date'] = $expirationDate;
            $updatedCase['district'] = $district;
            $updatedCase['prosecutor'] = $prosecutorName;
            $updatedCase['judge'] = $judgeName;
            $updatedCase['bail_amount'] = $bailAmount;
            $updatedCase['witnesses'] = $witnesses;
            $updatedCase['victims'] = $victims;
            
            // Neue Notiz hinzufügen, wenn vorhanden
            if (!empty($newNote)) {
                // Stelle sicher, dass notes ein Array ist
                if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                    $updatedCase['notes'] = [];
                }
                
                // Neue Notiz am Anfang des Arrays hinzufügen
                array_unshift($updatedCase['notes'], [
                    'date' => date('Y-m-d H:i:s'),
                    'user' => $username,
                    'note' => $newNote
                ]);
            }
            $updatedCase['date_updated'] = date('Y-m-d H:i:s');
            
            if (updateRecord('cases.json', $caseId, $updatedCase)) {
                $message = 'Fall wurde erfolgreich aktualisiert.';
                $caseData = $updatedCase; // Aktualisiere die Daten für die Anzeige
            } else {
                $error = 'Fehler beim Aktualisieren des Falls.';
            }
        }
    }
    // Revisionsantrag
    elseif (isset($_POST['action']) && $_POST['action'] === 'request_revision') {
        $revisionReason = sanitize($_POST['revision_reason'] ?? '');
        
        if (empty($revisionReason)) {
            $error = 'Bitte geben Sie einen Grund für die Revision an.';
        } else {
            $updatedCase = $caseData;
            
            // Status auf "Revision beantragt" setzen
            $updatedCase['status'] = 'revision_requested';
            $updatedCase['revision_requested_by'] = $username;
            $updatedCase['revision_requested_date'] = date('Y-m-d H:i:s');
            $updatedCase['revision_reason'] = $revisionReason;
            
            // Notiz hinzufügen
            $revisionNote = "Revision beantragt von " . $username . " am " . date('d.m.Y H:i:s') . "\nGrund: " . $revisionReason;
            
            // Stelle sicher, dass notes ein Array ist
            if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                $updatedCase['notes'] = [];
            }
            
            // Neue Notiz am Anfang des Arrays hinzufügen
            array_unshift($updatedCase['notes'], [
                'date' => date('Y-m-d H:i:s'),
                'user' => $username,
                'note' => $revisionNote
            ]);
            
            if (updateRecord('cases.json', $caseId, $updatedCase)) {
                $message = 'Revisionsantrag wurde erfolgreich eingereicht.';
                $caseData = $updatedCase; // Aktualisiere die Daten für die Anzeige
            } else {
                $error = 'Fehler beim Einreichen des Revisionsantrags.';
            }
        }
    }
    // Klageschrift einreichen
    elseif (isset($_POST['action']) && $_POST['action'] === 'submit_indictment') {
        $indictmentText = sanitize($_POST['indictment_text'] ?? '');
        
        // Überprüfen, ob die Akte vollständig ist und alle notwendigen Informationen enthält
        if (empty($caseData['defendant']) || empty($caseData['charge']) || empty($caseData['incident_date'])) {
            $error = 'Die Akte ist unvollständig. Bitte fügen Sie zuerst alle notwendigen Falldetails hinzu (Angeklagter, Anklage, Datum des Vorfalls).';
        }
        elseif (empty($indictmentText)) {
            $error = 'Die Klageschrift darf nicht leer sein.';
        } else {
            // Ersetze Signatur-Platzhalter mit tatsächlicher Signatur (mit Jahr 1899)
            $currentUser = getUserById($user_id);
            $signature = getTemplateSignature($currentUser); // Hier die Signatur mit 1899 verwenden
            $indictmentText = str_replace('{{SIGNATURE}}', $signature, $indictmentText);
            
            $updatedCase = $caseData;
            
            // Fallstatus aktualisieren
            $updatedCase['status'] = 'pending';
            $updatedCase['prosecutor'] = $username;
            
            // Speichere die Klageschrift
            $indictmentData = [
                'case_id' => $caseId,
                'content' => $indictmentText,
                'prosecutor_id' => $user_id,
                'prosecutor_name' => $username,
                'status' => 'pending',
                'date_created' => date('Y-m-d H:i:s')
            ];
            
            // Notiz zum Fall hinzufügen
            $indictmentNote = "Klageschrift eingereicht von " . $username . " am " . date('d.m.Y H:i:s');
            
            // Stelle sicher, dass notes ein Array ist
            if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                $updatedCase['notes'] = [];
            }
            
            // Neue Notiz am Anfang des Arrays hinzufügen
            array_unshift($updatedCase['notes'], [
                'date' => date('Y-m-d H:i:s'),
                'user' => $username,
                'note' => $indictmentNote
            ]);
            
            // Die insertRecord-Funktion gibt die ID zurück, wenn erfolgreich
            $indictmentId = insertRecord('indictments.json', $indictmentData);
            
            if ($indictmentId && updateRecord('cases.json', $caseId, $updatedCase)) {
                $message = 'Klageschrift wurde erfolgreich eingereicht.';
                
                // Aktualisiere Fall-Daten und Klageschrift-Daten
                $caseData = $updatedCase;
                $existingIndictment = findById('indictments.json', $indictmentId);
                
                // Weiterleitung zur Fallübersicht nach erfolgreicher Einreichung
                header('Location: cases.php');
                exit;
            } else {
                $error = 'Fehler beim Einreichen der Klageschrift.';
            }
        }
    }
    // Fall schließen
    elseif (isset($_POST['action']) && $_POST['action'] === 'close_case') {
        $closingReason = sanitize($_POST['closing_reason'] ?? '');
        
        if (empty($closingReason)) {
            $error = 'Bitte geben Sie einen Grund für die Schließung an.';
        } else {
            $updatedCase = $caseData;
            
            // Status auf "abgeschlossen" setzen
            $updatedCase['status'] = 'completed';
            $updatedCase['is_closed'] = true;
            $updatedCase['closed_date'] = date('Y-m-d H:i:s');
            $updatedCase['closed_reason'] = $closingReason;
            $updatedCase['closed_by'] = $user_id;  // Korrigiert von $userId
            $updatedCase['closed_by_name'] = $username;
            
            // Notiz hinzufügen
            $closeNote = "Fall geschlossen von " . $username . " am " . date('d.m.Y H:i:s') . " mit Grund: " . $closingReason;
            
            // Stelle sicher, dass notes ein Array ist
            if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                $updatedCase['notes'] = [];
            }
            
            // Neue Notiz am Anfang des Arrays hinzufügen
            array_unshift($updatedCase['notes'], [
                'date' => date('Y-m-d H:i:s'),
                'user' => $username,
                'note' => $closeNote
            ]);
            
            if (updateRecord('cases.json', $caseId, $updatedCase)) {
                $message = 'Fall wurde erfolgreich geschlossen.';
                $caseData = $updatedCase; // Aktualisiere die Daten für die Anzeige
                
                // Wenn eine Klageschrift existiert, aktualisiere auch diese
                if ($existingIndictment) {
                    $existingIndictment['status'] = 'completed';
                    $existingIndictment['case_closed'] = true;
                    $existingIndictment['case_closed_date'] = date('Y-m-d H:i:s');
                    $existingIndictment['case_closed_reason'] = $closingReason;
                    updateRecord('indictments.json', $existingIndictment['id'], $existingIndictment);
                }
                
                // Weiterleitung zur Fallübersicht
                header('Location: cases.php');
                exit;
            } else {
                $error = 'Fehler beim Schließen des Falls.';
            }
        }
    }
    // Aktenzeichen aktualisieren
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_case_id') {
        $newCaseId = sanitize($_POST['new_case_id'] ?? '');
        
        if (empty($newCaseId)) {
            $error = 'Das neue Aktenzeichen darf nicht leer sein.';
        } else {
            // Prüfen, ob das neue Aktenzeichen bereits existiert
            $existingCase = findById('cases.json', $newCaseId);
            if ($existingCase) {
                $error = 'Ein Fall mit diesem Aktenzeichen existiert bereits.';
            } else {
                // Speichere den alten Case-ID für Updates bei verknüpften Daten
                $oldCaseId = $caseId;
                
                // Aktualisiere den Case selbst
                $updatedCase = $caseData;
                $updatedCase['id'] = $newCaseId;
                $updatedCase['date_updated'] = date('Y-m-d H:i:s');
                
                // Erstelle neuen Datensatz mit neuer ID und lösche den alten
                if (insertRecord('cases.json', $updatedCase) && deleteRecord('cases.json', $oldCaseId)) {
                    // Aktualisiere Verweise in anderen Tabellen (z.B. Klageschriften)
                    if ($existingIndictment) {
                        $existingIndictment['case_id'] = $newCaseId;
                        updateRecord('indictments.json', $existingIndictment['id'], $existingIndictment);
                    }
                    
                    $message = 'Aktenzeichen wurde erfolgreich aktualisiert.';
                    
                    // Weiterleitung zur neuen Case-ID
                    header('Location: case_edit.php?id=' . $newCaseId);
                    exit;
                } else {
                    $error = 'Fehler beim Aktualisieren des Aktenzeichens.';
                }
            }
        }
    }
    // Außergerichtlicher Deal
    elseif (isset($_POST['action']) && $_POST['action'] === 'settlement') {
        $settlementDetails = sanitize($_POST['settlement_details'] ?? '');
        
        if (empty($settlementDetails)) {
            // Prüfe, ob ein Plea Deal mit Terms existiert, der verwendet werden kann
            if (isset($caseData['plea_deal']) && !empty($caseData['plea_deal']['terms'])) {
                $settlementDetails = $caseData['plea_deal']['terms'];
            } else {
                $error = 'Die Details des außergerichtlichen Deals dürfen nicht leer sein.';
            }
        }
        
        if (empty($error)) {
            $updatedCase = $caseData;
            
            // Status auf "abgeschlossen" setzen
            $updatedCase['status'] = 'completed';
            $updatedCase['is_closed'] = true;
            $updatedCase['closed_date'] = date('Y-m-d H:i:s');
            $updatedCase['closed_reason'] = 'Außergerichtlicher Deal';
            $updatedCase['settlement_details'] = $settlementDetails;
            
            // Notiz hinzufügen
            $settlementNote = "Außergerichtlicher Deal abgeschlossen von " . $username . " am " . date('d.m.Y H:i:s') . "\nDetails: " . $settlementDetails;
            
            // Stelle sicher, dass notes ein Array ist
            if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                $updatedCase['notes'] = [];
            }
            
            // Neue Notiz am Anfang des Arrays hinzufügen
            array_unshift($updatedCase['notes'], [
                'date' => date('Y-m-d H:i:s'),
                'user' => $username,
                'note' => $settlementNote
            ]);
            
            if (updateRecord('cases.json', $caseId, $updatedCase)) {
                $message = 'Außergerichtlicher Deal wurde erfolgreich registriert.';
                $caseData = $updatedCase; // Aktualisiere die Daten für die Anzeige
                
                // Wenn eine Klageschrift existiert, aktualisiere auch diese
                if ($existingIndictment) {
                    $existingIndictment['status'] = 'completed';
                    $existingIndictment['settlement'] = true;
                    $existingIndictment['settlement_details'] = $settlementDetails;
                    $existingIndictment['settlement_date'] = date('Y-m-d H:i:s');
                    $existingIndictment['settlement_by'] = $username;
                    
                    updateRecord('indictments.json', $existingIndictment['id'], $existingIndictment);
                }
                
                // Weiterleitung zur Fallübersicht nach erfolgreicher Eintragung
                header('Location: cases.php');
                exit;
            } else {
                $error = 'Fehler beim Registrieren des außergerichtlichen Deals.';
            }
        }
    }

    // Urteil zum Fall hinzufügen
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_verdict') {
        $verdictText = sanitize($_POST['verdict_text'] ?? '');
        $verdictDate = sanitize($_POST['verdict_date'] ?? '');
        $verdictStatus = sanitize($_POST['verdict_status'] ?? 'completed');
        
        if (empty($verdictText)) {
            $error = 'Der Urteilsspruch darf nicht leer sein.';
        } else {
            $updatedCase = $caseData;
            $updatedCase['status'] = $verdictStatus;
            $updatedCase['verdict'] = $verdictText;
            $updatedCase['verdict_date'] = $verdictDate;
            $updatedCase['verdict_by'] = $user_id;
            
            // Notiz zum Fall hinzufügen
            $verdictNote = "Urteil hinzugefügt von " . $username . " am " . date('d.m.Y H:i:s') . "\n";
            $verdictNote .= "Urteilsstatus: " . mapStatusToGerman($verdictStatus);
            
            // Stelle sicher, dass notes ein Array ist
            if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                $updatedCase['notes'] = [];
            }
            
            // Neue Notiz am Anfang des Arrays hinzufügen
            array_unshift($updatedCase['notes'], [
                'date' => date('Y-m-d H:i:s'),
                'user' => $username,
                'note' => $verdictNote
            ]);
            
            // Aktualisiere auch alle zugehörigen Klageschriften
            $indictments = loadJsonData('indictments.json');
            $updatedIndictments = [];
            
            foreach ($indictments as $indictment) {
                if (isset($indictment['case_id']) && $indictment['case_id'] === $caseId) {
                    // Update status nur für angenommene Klageschriften
                    if ($indictment['status'] === 'accepted' || $indictment['status'] === 'scheduled') {
                        $indictment['status'] = 'completed';
                        $indictment['verdict'] = $verdictText;
                        $indictment['verdict_date'] = $verdictDate;
                    }
                }
                $updatedIndictments[] = $indictment;
            }
            
            // Speichere aktualisierte Klageschriften
            saveJsonData('indictments.json', $updatedIndictments);
            
            if (updateRecord('cases.json', $caseId, $updatedCase)) {
                $message = 'Urteil wurde erfolgreich hinzugefügt.';
                $caseData = $updatedCase;
                
                // Weiterleitung zur Fallansicht nach erfolgreichem Hinzufügen des Urteils
                header('Location: case_view.php?id=' . $caseId);
                exit;
            } else {
                $error = 'Fehler beim Hinzufügen des Urteils.';
            }
        }
    }
    // Revisionsurteil eintragen
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_revision_verdict') {
        $revisionVerdictText = sanitize($_POST['revision_verdict_text'] ?? '');
        $revisionVerdictDate = sanitize($_POST['revision_verdict_date'] ?? date('Y-m-d'));
        $revisionVerdictStatus = sanitize($_POST['revision_verdict_status'] ?? 'completed');
        
        if (empty($revisionVerdictText)) {
            $error = 'Das Revisionsurteil darf nicht leer sein.';
        } else {
            $updatedCase = $caseData;
            
            // Status nach Urteil setzen
            $updatedCase['status'] = $revisionVerdictStatus;
            $updatedCase['revision_verdict'] = $revisionVerdictText;
            $updatedCase['revision_verdict_date'] = $revisionVerdictDate;
            $updatedCase['revision_verdict_by'] = $username;
            $updatedCase['revision_completed_date'] = date('Y-m-d H:i:s');
            
            // Notiz zum Fall hinzufügen
            $verdictNote = "Revisionsurteil eingegeben von " . $username . " am " . date('d.m.Y H:i:s') . "\n";
            $verdictNote .= "Urteilsstatus: " . mapStatusToGerman($revisionVerdictStatus) . "\n\n";
            $verdictNote .= $revisionVerdictText;
            
            // Stelle sicher, dass notes ein Array ist
            if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                $updatedCase['notes'] = [];
            }
            
            // Neue Notiz am Anfang des Arrays hinzufügen
            array_unshift($updatedCase['notes'], [
                'date' => date('Y-m-d H:i:s'),
                'user' => $username,
                'note' => $verdictNote
            ]);
            
            if (updateRecord('cases.json', $caseId, $updatedCase)) {
                $message = 'Revisionsurteil wurde erfolgreich hinzugefügt.';
                $caseData = $updatedCase;
                
                // Weiterleitung zur Fallansicht nach erfolgreichem Hinzufügen des Urteils
                header('Location: case_view.php?id=' . $caseId);
                exit;
            } else {
                $error = 'Fehler beim Hinzufügen des Revisionsurteils.';
            }
        }
    }
    // Verarbeitung eines außergerichtlichen Deal-Angebots (Annahme oder Ablehnung)
    elseif (isset($_POST['action']) && $_POST['action'] === 'process_plea_deal') {
        // Überprüfe, ob ein Plea Deal für diesen Fall vorhanden ist
        if (!isset($caseData['plea_deal']) || empty($caseData['plea_deal'])) {
            $error = 'Für diesen Fall wurde kein außergerichtlicher Deal angeboten.';
        } else {
            $pleaDealResponse = sanitize($_POST['plea_deal_response'] ?? '');
            
            if (!in_array($pleaDealResponse, ['accepted', 'rejected'])) {
                $error = 'Ungültige Antwort auf den außergerichtlichen Deal.';
            } else {
                $updatedCase = $caseData;
                
                // Aktualisiere den Plea Deal Status
                $updatedCase['plea_deal']['status'] = $pleaDealResponse;
                $updatedCase['plea_deal']['date_processed'] = date('Y-m-d H:i:s');
                $updatedCase['plea_deal']['processed_by'] = $username;
                $updatedCase['plea_deal']['processed_by_id'] = $user_id;
                
                // Aktualisiere den Fallstatus basierend auf der Antwort
                if ($pleaDealResponse === 'accepted') {
                    $updatedCase['status'] = 'plea_deal_accepted';
                    $statusMessage = 'angenommen';
                } else {
                    $updatedCase['status'] = 'plea_deal_rejected';
                    $statusMessage = 'abgelehnt';
                }
                
                // Aktualisiere die Fall-Notizen
                $dealNote = "Außergerichtlicher Deal wurde $statusMessage von $username am " . date('d.m.Y H:i:s') . "\n";
                
                // Stelle sicher, dass notes ein Array ist
                if (!isset($updatedCase['notes']) || !is_array($updatedCase['notes'])) {
                    $updatedCase['notes'] = [];
                }
                
                // Neue Notiz am Anfang des Arrays hinzufügen
                array_unshift($updatedCase['notes'], [
                    'date' => date('Y-m-d H:i:s'),
                    'user' => $username,
                    'note' => $dealNote
                ]);
                
                // Aktualisiere den Fall in der Datenbank
                if (updateRecord('cases.json', $caseId, $updatedCase)) {
                    $message = "Außergerichtlicher Deal wurde erfolgreich $statusMessage.";
                    $caseData = $updatedCase;
                    
                    // Weiterleitung zur Fallansicht nach erfolgreicher Verarbeitung
                    header('Location: case_view.php?id=' . $caseId . '&message=' . urlencode($message));
                    exit;
                } else {
                    $error = 'Fehler bei der Verarbeitung des außergerichtlichen Deals.';
                }
            }
        }
    }
}

// Vorbereitung der Klageschriftvorlage
$indictmentTemplate = '';
if (!$existingIndictment) {
    $dateFormatted = date('d.m.Y');
    $indictmentTemplate = "Klageschrift

Angeklagter: {$caseData['defendant']}
Anklage: {$caseData['charge']}
Vorfalldatum: " . formatDate($caseData['incident_date'], 'd.m.Y') . "
Bezirk: {$caseData['district']}

SACHVERHALT:
[Beschreiben Sie hier den Sachverhalt]

TATBESTAND:
[Beschreiben Sie hier den Tatbestand]

BEGRÜNDUNG:
[Geben Sie hier die rechtliche Begründung an]

ANTRAG:
Der Angeklagte, {$caseData['defendant']}, wird wegen {$caseData['charge']} angeklagt.

Datum: {$dateFormatted}

{{SIGNATURE}}
(Staatsanwalt)";
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Fall bearbeiten</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group mr-2">
                        <a href="cases.php" class="btn btn-sm btn-outline-secondary">Zurück zur Fallübersicht</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Fall-Informationen -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Fallinformationen</h4>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#editCaseIDModal">
                        <span data-feather="edit"></span> Aktenzeichen bearbeiten
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($error) && $error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($message) && $message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($showCloseForm) && $showCloseForm): ?>
                    <!-- Hinweis: Inline-Fallschließung entfernt, Umstellung auf Tab-basierte Oberfläche -->
                    <div class="alert alert-info">
                        <p>Die Fallschließungsfunktion wurde in die Tab-Ansicht verschoben. Bitte nutzen Sie den Tab "Fall schließen" oben.</p>
                        <a href="#close" data-toggle="tab" class="btn btn-primary">Zum "Fall schließen" Tab wechseln</a>
                        <a href="case_view.php?id=<?php echo $caseId; ?>" class="btn btn-secondary">Abbrechen</a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Aktenzeichen:</strong> <?php echo htmlspecialchars($caseData['id']); ?></p>
                            <p><strong>Angeklagter:</strong> <?php echo htmlspecialchars($caseData['defendant']); ?></p>
                            <p><strong>Anklage:</strong> <?php echo htmlspecialchars($caseData['charge']); ?></p>
                            <p><strong>Vorfalldatum:</strong> <?php echo isset($caseData['incident_date']) ? formatDate($caseData['incident_date'], 'd.m.Y') : 'Nicht angegeben'; ?></p>
                            <p><strong>Bezirk:</strong> <?php echo isset($caseData['district']) ? htmlspecialchars($caseData['district']) : 'Nicht angegeben'; ?></p>
                        </div>
                        
                        <div class="col-md-6">
                            <p><strong>Status:</strong> 
                                <?php
                                $statusText = mapStatusToGerman($caseData['status']);
                                $statusClass = 'secondary';
                                switch($caseData['status']) {
                                    case 'open': $statusClass = 'info'; break;
                                    case 'in_progress': $statusClass = 'primary'; break;
                                    case 'pending': $statusClass = 'warning'; break;
                                    case 'accepted': $statusClass = 'success'; break;
                                    case 'scheduled': $statusClass = 'primary'; break;
                                    case 'completed': $statusClass = 'dark'; break;
                                    case 'rejected': $statusClass = 'danger'; break;
                                    case 'dismissed': $statusClass = 'secondary'; break;
                                    case 'revision_requested': $statusClass = 'warning'; break;
                                }
                                echo '<span class="badge badge-' . $statusClass . '">' . $statusText . '</span>';
                                ?>
                            </p>
                            <p><strong>Staatsanwalt:</strong> <?php echo htmlspecialchars($caseData['prosecutor'] ?? 'Nicht zugewiesen'); ?></p>
                            <p><strong>Richter:</strong> <?php echo htmlspecialchars($caseData['judge'] ?? 'Nicht zugewiesen'); ?></p>
                            <p><strong>Erstellt am:</strong> <?php echo formatDate($caseData['date_created'], 'd.m.Y H:i'); ?></p>
                            <?php if (isset($caseData['date_updated'])): ?>
                                <p><strong>Zuletzt aktualisiert:</strong> <?php echo formatDate($caseData['date_updated'], 'd.m.Y H:i'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($caseData['notes'])): ?>
                        <div class="mt-3">
                            <h5>Notizen</h5>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?php if (is_array($caseData['notes'])): ?>
                                        <?php foreach ($caseData['notes'] as $note): ?>
                                            <div class="mb-2 p-2 border-bottom">
                                                <strong><?php echo htmlspecialchars($note['date'] ?? ''); ?> - <?php echo htmlspecialchars($note['user'] ?? ''); ?></strong>
                                                <pre class="mb-0 mt-1" style="font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($note['note'] ?? ''); ?></pre>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <pre class="mb-0" style="font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($caseData['notes']); ?></pre>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tabs für verschiedene Aktionen -->
            <ul class="nav nav-tabs" id="caseTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="edit-tab" data-toggle="tab" href="#edit" role="tab" aria-controls="edit" aria-selected="true">Fall bearbeiten</a>
                </li>
                <?php if (!$existingIndictment && ($isProsecutor || $isLeadership)): ?>
                    <li class="nav-item">
                        <a class="nav-link" id="indictment-tab" data-toggle="tab" href="#indictment" role="tab" aria-controls="indictment" aria-selected="false">Klageschrift einreichen</a>
                    </li>
                <?php endif; ?>
                <?php if ($existingIndictment): ?>
                    <li class="nav-item">
                        <a class="nav-link" id="view-indictment-tab" data-toggle="tab" href="#view-indictment" role="tab" aria-controls="view-indictment" aria-selected="false">Klageschrift ansehen</a>
                    </li>
                <?php endif; ?>
                <?php if (($caseData['status'] === 'open' || $caseData['status'] === 'in_progress' || $caseData['status'] === 'pending') && ($isProsecutor || $isLeadership)): ?>
                    <li class="nav-item">
                        <a class="nav-link" id="settlement-tab" data-toggle="tab" href="#settlement" role="tab" aria-controls="settlement" aria-selected="false">Außergerichtlicher Deal</a>
                    </li>
                <?php endif; ?>
                <?php 
                // Staatsanwälte und Führungskräfte dürfen Fälle schließen
                $userRole = $_SESSION['role'];
                // Diese Funktion berücksichtigt alle Rollen der Staatsanwaltschaft, einschließlich Senior Prosecutor
                $isProsecutor = checkUserHasRoleType($userRole, 'prosecutor');
                
                // Auch hier die Funktion zur konsistenten Rollenüberprüfung verwenden
                $isLeadership = checkUserHasRoleType($userRole, 'leadership');
                
                // Debug-Info
                error_log("Tabs - Rollenprüfung: " . $_SESSION['role'] . 
                      ", isProsecutor: " . ($isProsecutor ? "true" : "false") . 
                      ", isLeadership: " . ($isLeadership ? "true" : "false"));
                
                if ($caseData['status'] !== 'completed' && $caseData['status'] !== 'dismissed' && 
                    ($role === 'Administrator' || $role === 'Richter' || $isProsecutor || $isLeadership)): 
                ?>
                    <li class="nav-item">
                        <a class="nav-link" id="close-tab" data-toggle="tab" href="#close" role="tab" aria-controls="close" aria-selected="false">Fall schließen</a>
                    </li>
                <?php endif; ?>
                <?php if (($caseData['status'] === 'completed' || $caseData['status'] === 'rejected') && ($isProsecutor || $isLeadership)): ?>
                    <li class="nav-item">
                        <a class="nav-link" id="revision-tab" data-toggle="tab" href="#revision" role="tab" aria-controls="revision" aria-selected="false">Revision beantragen</a>
                    </li>
                <?php endif; ?>
                <?php
                // Prüfe auf Richterrolle
                $isJudge = (strtolower($userRole) == 'judge' || strtolower($userRole) == 'richter');
                
                if (strpos($caseData['status'], 'revision') !== false && ($isJudge || $isLeadership)): ?>
                    <li class="nav-item">
                        <a class="nav-link" id="revision_verdict-tab" data-toggle="tab" href="#revision_verdict" role="tab" aria-controls="revision_verdict" aria-selected="false">Revisionsurteil</a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <div class="tab-content" id="caseTabsContent">
                <!-- Tab: Fall bearbeiten -->
                <div class="tab-pane fade show active" id="edit" role="tabpanel" aria-labelledby="edit-tab">
                    <div class="card border-top-0 rounded-top-0">
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="update_case">
                                
                                <div class="form-group">
                                    <label for="defendant">Angeklagter *</label>
                                    <input type="text" class="form-control" id="defendant" name="defendant" value="<?php echo htmlspecialchars($caseData['defendant']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="charge">Anklage *</label>
                                    <input type="text" class="form-control" id="charge" name="charge" value="<?php echo htmlspecialchars($caseData['charge']); ?>" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="incident_date">Vorfalldatum *</label>
                                        <input type="date" class="form-control" id="incident_date" name="incident_date" value="<?php echo isset($caseData['incident_date']) ? htmlspecialchars($caseData['incident_date']) : date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="form-group col-md-6">
                                        <label for="district">Bezirk *</label>
                                        <select class="form-control" id="district" name="district" required>
                                            <option value="">-- Bitte wählen --</option>
                                            <option value="Ost" <?php if (isset($caseData['district']) && $caseData['district'] === 'Ost') echo 'selected'; ?>>Ost</option>
                                            <option value="West" <?php if (isset($caseData['district']) && $caseData['district'] === 'West') echo 'selected'; ?>>West</option>
                                            <option value="Nord" <?php if (isset($caseData['district']) && $caseData['district'] === 'Nord') echo 'selected'; ?>>Nord</option>
                                            <option value="Süd" <?php if (isset($caseData['district']) && $caseData['district'] === 'Süd') echo 'selected'; ?>>Süd</option>
                                            <option value="Zentral" <?php if (isset($caseData['district']) && $caseData['district'] === 'Zentral') echo 'selected'; ?>>Zentral</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="prosecutor">Staatsanwalt</label>
                                        <input type="text" class="form-control" id="prosecutor" name="prosecutor" value="<?php echo htmlspecialchars($caseData['prosecutor'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group col-md-6">
                                        <label for="judge">Richter</label>
                                        <input type="text" class="form-control" id="judge" name="judge" value="<?php echo htmlspecialchars($caseData['judge'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="bail_amount">Kaution ($ Dollar)</label>
                                    <input type="number" class="form-control" id="bail_amount" name="bail_amount" value="<?php echo htmlspecialchars($caseData['bail_amount'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="witnesses">Zeugen (mit TG-Nr.)</label>
                                    <textarea class="form-control" id="witnesses" name="witnesses" rows="3"><?php echo htmlspecialchars($caseData['witnesses'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Format: Name, TG-Nr. (Ein Zeuge pro Zeile)</small>
                                </div>

                                <div class="form-group">
                                    <label for="victims">Geschädigte (mit TG-Nr.)</label>
                                    <textarea class="form-control" id="victims" name="victims" rows="3"><?php echo htmlspecialchars($caseData['victims'] ?? ''); ?></textarea>
                                    <small class="form-text text-muted">Format: Name, TG-Nr. (Ein Geschädigter pro Zeile)</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">Neue Notiz hinzufügen</label>
                                    <textarea class="form-control" id="notes" name="new_note" rows="4"></textarea>
                                    <small class="form-text text-muted">Notizen werden als Verlauf gespeichert und können nicht mehr bearbeitet werden.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Fall aktualisieren</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Tab: Klageschrift einreichen -->
                <?php if (!$existingIndictment && ($isProsecutor || $isLeadership)): ?>
                    <div class="tab-pane fade" id="indictment" role="tabpanel" aria-labelledby="indictment-tab">
                        <div class="card border-top-0 rounded-top-0">
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="submit_indictment">
                                    
                                    <!-- Anklagepunkte manuell festlegen -->
                                    <div class="form-group">
                                        <label for="charges">Anklagepunkte *</label>
                                        <div id="charges-container">
                                            <div class="mb-2 charge-item">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="charges[]" required placeholder="z.B. §131 StGB - Raub">
                                                    <div class="input-group-append">
                                                        <button type="button" class="btn btn-danger remove-charge" style="display:none;">-</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-secondary mt-2" id="add-charge">Weiteren Anklagepunkt hinzufügen</button>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="indictment_text">Klageschrift *</label>
                                        <?php 
                                        // Standardvorlage für Klageschriften
                                        $incident_date = isset($caseData['incident_date']) ? date('d.m.Y', strtotime($caseData['incident_date'])) : date('d.m.Y');
                                        $district = isset($caseData['district']) ? $caseData['district'] : 'unbekannt';
                                        
                                        $indictmentTemplate = "KLAGESCHRIFT\n\nGegen: {$caseData['defendant']}\n\nVerbrechen: {$caseData['charge']}\nDatum des Verbrechens: {$incident_date}\nOrt des Verbrechens: {$district}\n\nBeschreibung des Sachverhalts:\n[Detaillierte Beschreibung des Sachverhalts einfügen]\n\nDer Angeklagte wird hiermit beschuldigt, am {$incident_date} in {$district} [Beschreibung der kriminellen Handlung] begangen zu haben, was einen Verstoß gegen [relevantes Gesetz/Paragraf] darstellt.\n\nDie Staatsanwaltschaft ersucht um Anklageerhebung.\n\nDatum: " . date('d.m.Y') . "\n\n{{SIGNATURE}}";
                                        ?>
                                        <textarea class="form-control" id="indictment_text" name="indictment_text" rows="15" required><?php echo htmlspecialchars($indictmentTemplate); ?></textarea>
                                        <small class="form-text text-muted">
                                            Verwenden Sie {{SIGNATURE}} als Platzhalter für Ihre Signatur.
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Klageschrift einreichen</button>
                                </form>

                                <script>
                                // JavaScript für die Anklagepunkte
                                document.addEventListener('DOMContentLoaded', function() {
                                    const chargesContainer = document.getElementById('charges-container');
                                    const addChargeButton = document.getElementById('add-charge');
                                    
                                    addChargeButton.addEventListener('click', function() {
                                        const newChargeItem = document.createElement('div');
                                        newChargeItem.className = 'mb-2 charge-item';
                                        newChargeItem.innerHTML = `
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="charges[]" required placeholder="z.B. §131 StGB - Raub">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-danger remove-charge">-</button>
                                                </div>
                                            </div>
                                        `;
                                        chargesContainer.appendChild(newChargeItem);
                                        
                                        // Aktiviere alle Lösch-Buttons, wenn mehr als ein Eintrag vorhanden ist
                                        const removeButtons = document.querySelectorAll('.remove-charge');
                                        if (removeButtons.length > 1) {
                                            removeButtons.forEach(btn => btn.style.display = 'block');
                                        }
                                    });
                                    
                                    // Event-Delegation für Lösch-Buttons
                                    chargesContainer.addEventListener('click', function(e) {
                                        if (e.target.classList.contains('remove-charge')) {
                                            e.target.closest('.charge-item').remove();
                                            
                                            // Wenn nur noch ein Eintrag übrig ist, verstecke den Lösch-Button
                                            const remainingButtons = document.querySelectorAll('.remove-charge');
                                            if (remainingButtons.length <= 1) {
                                                remainingButtons.forEach(btn => btn.style.display = 'none');
                                            }
                                        }
                                    });
                                });
                                </script>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Tab: Klageschrift ansehen -->
                <?php if ($existingIndictment): ?>
                    <div class="tab-pane fade" id="view-indictment" role="tabpanel" aria-labelledby="view-indictment-tab">
                        <div class="card border-top-0 rounded-top-0">
                            <div class="card-body">
                                <div class="mb-3">
                                    <h5>Status: 
                                        <?php 
                                            $indictmentStatusText = mapStatusToGerman($existingIndictment['status']);
                                            $indictmentStatusClass = 'secondary';
                                            switch($existingIndictment['status']) {
                                                case 'pending': $indictmentStatusClass = 'warning'; break;
                                                case 'accepted': $indictmentStatusClass = 'success'; break;
                                                case 'scheduled': $indictmentStatusClass = 'primary'; break;
                                                case 'completed': $indictmentStatusClass = 'dark'; break;
                                                case 'rejected': $indictmentStatusClass = 'danger'; break;
                                            }
                                            echo '<span class="badge badge-' . $indictmentStatusClass . '">' . $indictmentStatusText . '</span>';
                                        ?>
                                    </h5>
                                    <p>Eingereicht von: <?php echo htmlspecialchars($existingIndictment['prosecutor_name']); ?></p>
                                    <p>Eingereicht am: <?php echo formatDate($existingIndictment['date_created'], 'd.m.Y H:i'); ?></p>
                                    
                                    <?php if (isset($existingIndictment['processor_name'])): ?>
                                        <p>Bearbeitet von: <?php echo htmlspecialchars($existingIndictment['processor_name']); ?></p>
                                        <p>Bearbeitet am: <?php echo formatDate($existingIndictment['process_date'], 'd.m.Y H:i'); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($existingIndictment['trial_date'])): ?>
                                        <p>Verhandlungstermin: <?php echo formatDate($existingIndictment['trial_date'], 'd.m.Y H:i'); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($existingIndictment['judgment']) && !empty($existingIndictment['judgment'])): ?>
                                        <h5>Begründung / Anmerkungen:</h5>
                                        <div class="card bg-light mb-3">
                                            <div class="card-body">
                                                <pre class="mb-0" style="font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($existingIndictment['judgment']); ?></pre>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <h5>Inhalt der Klageschrift:</h5>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <pre class="mb-0" style="font-family: inherit; white-space: pre-wrap;"><?php echo htmlspecialchars($existingIndictment['content']); ?></pre>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="../modules/indictments.php?id=<?php echo $existingIndictment['id']; ?>&view=detail" class="btn btn-primary">
                                        Zur Klageschrift-Detailansicht
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Tab: Außergerichtlicher Deal -->
                <?php if (($caseData['status'] === 'open' || $caseData['status'] === 'in_progress' || $caseData['status'] === 'pending') && ($isProsecutor || $isLeadership)): ?>
                    <div class="tab-pane fade" id="settlement" role="tabpanel" aria-labelledby="settlement-tab">
                        <div class="card border-top-0 rounded-top-0">
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <strong>Achtung:</strong> Ein außergerichtlicher Deal wird den Fall abschließen und als erledigt markieren.
                                </div>
                                
                                <form method="post" action="" onsubmit="return confirm('Sind Sie sicher, dass Sie einen außergerichtlichen Deal registrieren möchten? Diese Aktion schließt den Fall ab.');">
                                    <input type="hidden" name="action" value="settlement">
                                    
                                    <div class="form-group">
                                        <label for="settlement_details">Details des außergerichtlichen Deals *</label>
                                        <?php 
                                        // Wenn ein Plea Deal existiert, vorausfüllen mit den Bedingungen
                                        $existingPleaDealTerms = '';
                                        if (isset($caseData['plea_deal']) && !empty($caseData['plea_deal']['terms'])) {
                                            $existingPleaDealTerms = $caseData['plea_deal']['terms'];
                                        }
                                        ?>
                                        <textarea class="form-control" id="settlement_details" name="settlement_details" rows="6" required><?php echo htmlspecialchars($existingPleaDealTerms); ?></textarea>
                                        <?php if (!empty($existingPleaDealTerms)): ?>
                                            <small class="form-text text-muted">Die Bedingungen wurden aus dem bestehenden außergerichtlichen Deal übernommen. Sie können sie bei Bedarf anpassen.</small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">Außergerichtlichen Deal registrieren</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Tab: Fall schließen -->
                <?php if ($caseData['status'] !== 'completed' && $caseData['status'] !== 'dismissed' && 
                         ($role === 'Administrator' || $role === 'Richter' || $isProsecutor || $isLeadership)): ?>
                    <div class="tab-pane fade" id="close" role="tabpanel" aria-labelledby="close-tab">
                        <div class="card border-top-0 rounded-top-0">
                            <div class="card-body">
                                <div class="alert alert-danger">
                                    <strong>Achtung:</strong> Durch das Schließen des Falls wird dieser als erledigt markiert und kann nicht mehr bearbeitet werden.
                                </div>
                                
                                <?php if (isset($error) && $error && isset($_POST['action']) && $_POST['action'] === 'close_case'): ?>
                                <div class="alert alert-warning">
                                    <strong>Fehler:</strong> <?php echo htmlspecialchars($error); ?>
                                </div>
                                <?php endif; ?>
                                
                                <form method="post" action="case_edit.php?id=<?php echo $caseId; ?>" id="closeForm" onsubmit="return confirm('Sind Sie sicher, dass Sie diesen Fall schließen möchten? Diese Aktion kann nicht rückgängig gemacht werden.');">
                                    <input type="hidden" name="action" value="close_case">
                                    
                                    <div class="form-group">
                                        <label for="closing_reason">Grund für die Schließung *</label>
                                        <select class="form-control" id="closing_reason" name="closing_reason" required>
                                            <option value="">-- Bitte wählen --</option>
                                            <option value="Eingestellt - mangelnde Beweise">Eingestellt - mangelnde Beweise</option>
                                            <option value="Eingestellt - kein öffentliches Interesse">Eingestellt - kein öffentliches Interesse</option>
                                            <option value="Übertragung an andere Gerichtsbarkeit">Übertragung an andere Gerichtsbarkeit</option>
                                            <option value="Zusammenlegung mit anderem Fall">Zusammenlegung mit anderem Fall</option>
                                            <option value="Administrativer Fehler">Administrativer Fehler</option>
                                            <option value="Sonstiges">Sonstiges</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-danger btn-lg">
                                        <i class="fas fa-times-circle"></i> Fall schließen
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Tab: Revision beantragen -->
                <?php if (($caseData['status'] === 'completed' || $caseData['status'] === 'rejected') && ($isProsecutor || $isLeadership)): ?>
                    <div class="tab-pane fade" id="revision" role="tabpanel" aria-labelledby="revision-tab">
                        <div class="card border-top-0 rounded-top-0">
                            <div class="card-body">
                                <form method="post" action="case_edit.php?id=<?php echo $caseId; ?>">
                                    <input type="hidden" name="action" value="request_revision">
                                    
                                    <div class="form-group">
                                        <label for="revision_reason">Grund für die Revision *</label>
                                        <textarea class="form-control" id="revision_reason" name="revision_reason" rows="6" required></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-warning">Revision beantragen</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Tab: Revisionsurteil eintragen (nur für Richter und Administrator) -->
                <?php if (strpos($caseData['status'], 'revision') !== false && ($isJudge || $isLeadership)): ?>
                    <div class="tab-pane fade" id="revision_verdict" role="tabpanel" aria-labelledby="revision_verdict-tab">
                        <div class="card border-top-0 rounded-top-0">
                            <div class="card-body">
                                <form method="post" action="case_edit.php?id=<?php echo $caseId; ?>">
                                    <input type="hidden" name="action" value="add_revision_verdict">
                                    
                                    <div class="form-group">
                                        <label for="revision_verdict_text">Revisionsurteil *</label>
                                        <textarea class="form-control" id="revision_verdict_text" name="revision_verdict_text" rows="6" required></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="revision_verdict_date">Urteilsdatum</label>
                                        <input type="date" class="form-control" id="revision_verdict_date" name="revision_verdict_date" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="revision_verdict_status">Fallstatus nach Urteil</label>
                                        <select class="form-control" id="revision_verdict_status" name="revision_verdict_status">
                                            <option value="revision_completed">Revision abgeschlossen</option>
                                            <option value="completed">Fall abgeschlossen</option>
                                            <option value="reopened">Fall wieder geöffnet</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-success">Revisionsurteil speichern</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<!-- Modal für die Bearbeitung des Aktenzeichens -->
<div class="modal fade" id="editCaseIDModal" tabindex="-1" aria-labelledby="editCaseIDModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCaseIDModalLabel">Aktenzeichen bearbeiten</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="case_edit.php?id=<?php echo $caseId; ?>">
                <input type="hidden" name="action" value="update_case_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Achtung:</strong> Das Ändern des Aktenzeichens kann zu Konsistenzproblemen führen. 
                        Stellen Sie sicher, dass das neue Aktenzeichen einzigartig ist.
                    </div>
                    <div class="form-group">
                        <label for="current_case_id">Aktuelles Aktenzeichen</label>
                        <input type="text" class="form-control" id="current_case_id" value="<?php echo htmlspecialchars($caseData['id']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="new_case_id">Neues Aktenzeichen *</label>
                        <input type="text" class="form-control" id="new_case_id" name="new_case_id" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Aktenzeichen aktualisieren</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>