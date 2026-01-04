<?php
/**
 * Aktenverwaltungssystem - Department of Justice
 * Klageschriften-Verwaltungsmodul
 * 
 * Dieses Modul ermöglicht die Erstellung, Bearbeitung und Verwaltung von Klageschriften.
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

// Standardmäßige Ansichtsmodi - werden später gegebenenfalls überschrieben
$editingIndictment = false;
$viewingIndictmentDetails = false;
$selectedIndictment = null;

// Verarbeitung der Aktionen für Klageschriften
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Gerichtsverhandlung terminieren
        if ($action === 'schedule_court_date') {
            $indictmentId = sanitize($_POST['indictment_id'] ?? '');
            $trialDate = sanitize($_POST['trial_date'] ?? '');
            $trialTime = sanitize($_POST['trial_time'] ?? '');
            $trialNotes = sanitize($_POST['trial_notes'] ?? '');
            
            // Kombiniere Datum und Uhrzeit
            $fullTrialDateTime = $trialDate . ' ' . $trialTime . ':00';
            
            $indictment = findById('indictments.json', $indictmentId);
            if (!$indictment) {
                $error = 'Klageschrift nicht gefunden.';
            } else {
                $caseData = findById('cases.json', $indictment['case_id']);
                if (!$caseData) {
                    $error = 'Zugehöriger Fall nicht gefunden.';
                } else {
                    // Aktualisiere Klageschrift
                    $indictment['status'] = 'scheduled';
                    $indictment['trial_date'] = $fullTrialDateTime;
                    $indictment['trial_notes'] = $trialNotes;
                    $indictment['scheduled_by'] = $user_id;
                    $indictment['scheduled_by_name'] = $username;
                    
                    // Stelle sicher, dass der Richter gesetzt ist
                    $indictment['judge_id'] = $user_id;
                    $indictment['judge_name'] = $username;
                    
                    // Debug-Ausgabe
                    error_log("SCHEDULEV2: Setting court date for indictment: id=$indictmentId, date=$fullTrialDateTime, judge=$username");
                    
                    // Aktualisiere Fall
                    $caseData['status'] = 'scheduled';
                    $caseData['trial_date'] = $fullTrialDateTime;
                    $caseData['trial_notes'] = $trialNotes;
                    
                    // Stelle sicher, dass der Richter im Fall gesetzt ist
                    $caseData['judge_id'] = $user_id;
                    $caseData['judge_name'] = $username;
                    $caseData['judge'] = $username;  // Legacy-Unterstützung
                    
                    // Debug-Ausgabe
                    error_log("SCHEDULEV2: Setting judge for case: id=" . $caseData['id'] . ", judge=$username");
                    
                    // Speichern der Aktualisierungen
                    if (updateRecord('indictments.json', $indictmentId, $indictment) && 
                        updateRecord('cases.json', $caseData['id'], $caseData)) {
                        $message = 'Gerichtstermin wurde erfolgreich geplant für ' . date('d.m.Y H:i', strtotime($fullTrialDateTime)) . ' Uhr.';
                        
                        // Weiterleitung zur Detailansicht nach erfolgreicher Terminierung
                        header('Location: indictments.php?id=' . $indictmentId . '&view=detail&message=' . urlencode($message));
                        exit;
                    } else {
                        $error = 'Fehler beim Speichern des Gerichtstermins.';
                    }
                }
            }
        }
        
        // Urteil eintragen
        if ($action === 'enter_verdict') {
            $indictmentId = sanitize($_POST['indictment_id'] ?? '');
            $verdict = sanitize($_POST['verdict'] ?? '');
            $verdictDate = sanitize($_POST['verdict_date'] ?? date('Y-m-d'));
            
            $indictment = findById('indictments.json', $indictmentId);
            if (!$indictment) {
                $error = 'Klageschrift nicht gefunden.';
            } else {
                $caseData = findById('cases.json', $indictment['case_id']);
                if (!$caseData) {
                    $error = 'Zugehöriger Fall nicht gefunden.';
                } else {
                    // Aktualisiere Klageschrift
                    $indictment['status'] = 'completed';
                    $indictment['verdict'] = $verdict;
                    $indictment['verdict_date'] = $verdictDate;
                    $indictment['verdict_by'] = $user_id;
                    $indictment['verdict_by_name'] = $username;
                    
                    // Aktualisiere Fall
                    $caseData['status'] = 'completed';
                    $caseData['verdict'] = $verdict;
                    $caseData['verdict_date'] = $verdictDate;
                    
                    // Stelle sicher, dass der Richter im Fall gesetzt ist
                    $caseData['judge_id'] = $user_id;
                    $caseData['judge_name'] = $username;
                    $caseData['judge'] = $username;  // Legacy-Unterstützung
                    
                    // Debug-Ausgabe
                    error_log("VERDICT: Setting judge for case: id=" . $caseData['id'] . ", judge=$username");
                    
                    // Speichern der Aktualisierungen
                    if (updateRecord('indictments.json', $indictmentId, $indictment) && 
                        updateRecord('cases.json', $caseData['id'], $caseData)) {
                        $message = 'Urteil wurde erfolgreich eingetragen.';
                        
                        // Weiterleitung zur Detailansicht nach erfolgreicher Urteilseintragung
                        header('Location: indictments.php?id=' . $indictmentId . '&view=detail&message=' . urlencode($message));
                        exit;
                    } else {
                        $error = 'Fehler beim Speichern des Urteils.';
                    }
                }
            }
        }
        
        // Aktualisieren einer angenommenen Klageschrift und Urteil hinzufügen
        if ($action === 'update_accepted_indictment') {
            $indictmentId = sanitize($_POST['indictment_id'] ?? '');
            $verdict = sanitize($_POST['verdict'] ?? '');
            $verdictDate = sanitize($_POST['verdict_date'] ?? date('Y-m-d'));
            $status = sanitize($_POST['status'] ?? 'completed');
            
            $indictment = findById('indictments.json', $indictmentId);
            if (!$indictment) {
                $error = 'Klageschrift nicht gefunden.';
            } else {
                $caseData = findById('cases.json', $indictment['case_id']);
                if (!$caseData) {
                    $error = 'Zugehöriger Fall nicht gefunden.';
                } else {
                    // Aktualisiere Klageschrift
                    $indictment['status'] = $status;
                    $indictment['verdict'] = $verdict;
                    $indictment['verdict_date'] = $verdictDate;
                    $indictment['verdict_by'] = $user_id;
                    $indictment['verdict_by_name'] = $username;
                    
                    // Aktualisiere Fall
                    $caseData['status'] = $status;
                    $caseData['verdict'] = $verdict;
                    $caseData['verdict_date'] = $verdictDate;
                    $caseData['verdict_by'] = $user_id;
                    
                    // Füge Notiz zum Fall hinzu
                    $verdictNote = "Urteil aktualisiert von " . $username . " am " . date('d.m.Y H:i:s');
                    $caseData['notes'] = (!empty($caseData['notes'])) 
                        ? $verdictNote . "\n\n" . $caseData['notes'] 
                        : $verdictNote;
                    
                    // Speichere Änderungen
                    if (updateRecord('indictments.json', $indictmentId, $indictment) &&
                        updateRecord('cases.json', $caseData['id'], $caseData)) {
                        $message = 'Klageschrift und Urteil wurden erfolgreich aktualisiert.';
                    } else {
                        $error = 'Fehler beim Aktualisieren der Klageschrift und des Urteils.';
                    }
                }
            }
        }
        
        // Erstellen oder Aktualisieren einer Klageschrift
        if ($action === 'create' || $action === 'update') {
            $caseId = sanitize($_POST['case_id'] ?? '');
            $content = sanitize($_POST['indictment_content'] ?? '');
            
            // Validierung
            if (empty($caseId) || empty($content)) {
                $error = 'Bitte füllen Sie alle erforderlichen Felder aus.';
            } else {
                // Überprüfe, ob der Fall existiert
                $case = findById('cases.json', $caseId);
                if (!$case) {
                    $error = 'Ungültiger Fall.';
                } else {
                    // Suche nach einer vorhandenen Klageschrift für diesen Fall
                    $indictments = getJsonData('indictments.json');
                    $existingIndictment = null;
                    
                    foreach ($indictments as $indictment) {
                        if ($indictment['case_id'] === $caseId) {
                            $existingIndictment = $indictment;
                            break;
                        }
                    }
                    
                    if ($existingIndictment && $action === 'update') {
                        // Aktualisiere vorhandene Klageschrift
                        $indictmentData = $existingIndictment;
                        $indictmentData['content'] = $content;
                        $indictmentData['date_updated'] = date('Y-m-d H:i:s');
                        
                        if (updateRecord('indictments.json', $existingIndictment['id'], $indictmentData)) {
                            $message = 'Klageschrift erfolgreich aktualisiert.';
                        } else {
                            $error = 'Fehler beim Aktualisieren der Klageschrift.';
                        }
                    } else if (!$existingIndictment && $action === 'create') {
                        // Erstelle neue Klageschrift
                        $indictmentData = [
                            'case_id' => $caseId,
                            'content' => $content,
                            'prosecutor_id' => $user_id,
                            'prosecutor_name' => $username,
                            'status' => 'pending',
                            'date_created' => date('Y-m-d H:i:s')
                        ];
                        
                        if (insertRecord('indictments.json', $indictmentData)) {
                            // Aktualisiere auch den Status des Falls
                            $case['status'] = 'pending';
                            $caseNote = "Klageschrift eingereicht von " . $username . " am " . date('d.m.Y H:i:s');
                            $case['notes'] = ($case['notes'] ? $caseNote . "\n\n" . $case['notes'] : $caseNote);
                            
                            if (updateRecord('cases.json', $caseId, $case)) {
                                $message = 'Klageschrift erfolgreich eingereicht und Fallstatus aktualisiert.';
                            } else {
                                $message = 'Klageschrift eingereicht, aber Fallstatus konnte nicht aktualisiert werden.';
                            }
                        } else {
                            $error = 'Fehler beim Erstellen der Klageschrift.';
                        }
                    } else if ($existingIndictment && $action === 'create') {
                        $error = 'Für diesen Fall existiert bereits eine Klageschrift.';
                    } else {
                        $error = 'Keine Klageschrift zum Aktualisieren gefunden.';
                    }
                }
            }
        }
        // Verarbeitung einer Klageschrift durch Richter/Administrator
        else if ($action === 'process_indictment') {
            $indictmentId = sanitize($_POST['indictment_id'] ?? '');
            $status = sanitize($_POST['status'] ?? '');
            $judgment = sanitize($_POST['judgment'] ?? '');
            $trial_date = sanitize($_POST['trial_date'] ?? '');
            
            if (empty($indictmentId) || empty($status)) {
                $error = 'Bitte geben Sie alle erforderlichen Informationen an.';
            } else {
                // Überprüfen der Berechtigung (nur Richter oder Administratoren)
                if ($role !== 'Richter' && $role !== 'Administrator') {
                    $error = 'Sie haben keine Berechtigung, Klageschriften zu verarbeiten.';
                } else {
                    $indictment = findById('indictments.json', $indictmentId);
                    
                    if (!$indictment) {
                        $error = 'Ungültige Klageschrift.';
                    } else {
                        // Setze Verarbeitungsinformationen
                        $indictment['processor_id'] = $user_id;
                        $indictment['processor_name'] = $username;
                        $indictment['process_date'] = date('Y-m-d H:i:s');
                        
                        // Setze den verarbeitenden Benutzer als Richter
                        $indictment['judge_id'] = $user_id;
                        $indictment['judge_name'] = $username;
                        
                        // Debug-Ausgabe
                        error_log("RICHTER: Setting judge for indictment: user_id=$user_id, username=$username");
                        
                        // Finde die zugehörige Case-Datei und setze dort ebenfalls den Richter
                        $caseId = $indictment['case_id'] ?? '';
                        if (!empty($caseId)) {
                            $caseData = findById('cases.json', $caseId);
                            if ($caseData) {
                                // Debug-Ausgabe
                                error_log("RICHTER: Updating case: id=$caseId, old judge=" . ($caseData['judge_name'] ?? 'none'));
                                
                                // WICHTIG: Hier wird der Richter explizit gesetzt
                                $caseData['judge_id'] = $user_id;
                                $caseData['judge_name'] = $username;
                                $caseData['judge'] = $username; // Für die ursprüngliche Fallakte
                                $caseData['judge_assignment_date'] = date('Y-m-d H:i:s');
                                
                                // Direkt in die cases.json-Datei schreiben
                                if (updateRecord('cases.json', $caseId, $caseData)) {
                                    error_log("RICHTER: Case updated successfully with judge: $username");
                                } else {
                                    error_log("RICHTER: Failed to update case with judge information");
                                }
                            }
                        }
                        
                        // Setze Status
                        $indictment['status'] = $status;
                        $indictment['decision_history'] = $indictment['decision_history'] ?? [];
                        
                        // Neue Entscheidung zur Historie hinzufügen
                        $newDecision = [
                            'status' => $status,
                            'date' => date('Y-m-d H:i:s'),
                            'processor_id' => $user_id,
                            'processor_name' => $username
                        ];
                        
                        // Zusätzliche Informationen je nach Status
                        if ($status === 'accepted') {
                            // Klageschrift angenommen
                            $newDecision['comment'] = $judgment;
                            $indictment['judgment'] = $judgment;
                            $indictment['acceptance_date'] = date('Y-m-d H:i:s');
                        } else if ($status === 'rejected') {
                            // Klageschrift abgelehnt
                            $newDecision['comment'] = $judgment;
                            $indictment['judgment'] = $judgment;
                            $indictment['rejection_date'] = date('Y-m-d H:i:s');
                        } else if ($status === 'scheduled') {
                            // Verhandlung terminiert
                            $newDecision['comment'] = $judgment;
                            $newDecision['trial_date'] = $trial_date;
                            $indictment['judgment'] = $judgment;
                            $indictment['trial_date'] = $trial_date;
                        } else if ($status === 'completed') {
                            // Erledigt nach Urteil
                            $newDecision['comment'] = $judgment;
                            $newDecision['verdict'] = $judgment;
                            $indictment['judgment'] = $judgment;
                            $indictment['verdict'] = $judgment;
                            $indictment['completed_date'] = date('Y-m-d H:i:s');
                        }
                        
                        // Füge die neue Entscheidung zur Historie hinzu
                        $indictment['decision_history'][] = $newDecision;
                        
                        if (updateRecord('indictments.json', $indictmentId, $indictment)) {
                            // Aktualisiere auch den Status des Falls
                            $case = findById('cases.json', $indictment['case_id']);
                            
                            if ($case) {
                                // Aktualisiere den Status je nach Aktion
                                $case['status'] = $status;
                                
                                // Setze auch für den Fall den Bearbeiter als Richter
                                $case['judge_id'] = $user_id;
                                $case['judge_name'] = $username;
                                $case['judge'] = $username;
                                
                                // Debug-Ausgabe
                                error_log("RICHTER 2: Setting judge for case in indictment processing: user_id=$user_id, username=$username, case_id=" . $case['id']);
                                
                                if ($status === 'rejected') {
                                    // Markiere den Fall als abgeschlossen
                                    $case['is_closed'] = true;
                                    $case['closed_date'] = date('Y-m-d H:i:s');
                                    $case['closed_reason'] = 'Klageschrift abgelehnt';
                                } else if ($status === 'scheduled') {
                                    $case['trial_date'] = $trial_date;
                                    
                                    // Füge Verhandlungstermin zum Kalender hinzu
                                    $calendarEvents = getJsonData('calendar.json');
                                    
                                    // Prüfe, ob bereits ein Termin für diesen Fall existiert
                                    $existingEventIndex = -1;
                                    foreach ($calendarEvents as $index => $event) {
                                        if (isset($event['case_id']) && $event['case_id'] === $case['id'] &&
                                            isset($event['event_type']) && $event['event_type'] === 'trial') {
                                            $existingEventIndex = $index;
                                            break;
                                        }
                                    }
                                    
                                    $eventData = [
                                        'title' => 'Gerichtsverhandlung: ' . $case['defendant'],
                                        'date' => $trial_date,
                                        'time' => substr($trial_date, 11, 5), // Extract HH:MM from the datetime
                                        'description' => 'Verhandlung für Klageschrift gegen ' . $case['defendant'] . 
                                                    ' wegen ' . $case['charge'] . 
                                                    ($judgment ? "\n\nAnmerkungen: " . $judgment : ''),
                                        'created_by' => $user_id,
                                        'created_by_name' => $username,
                                        'event_type' => 'trial',
                                        'case_id' => $case['id'],
                                        'indictment_id' => $indictment['id']
                                    ];
                                    
                                    if ($existingEventIndex >= 0) {
                                        // Aktualisiere vorhandenen Termin
                                        $eventData['id'] = $calendarEvents[$existingEventIndex]['id'];
                                        $calendarEvents[$existingEventIndex] = $eventData;
                                    } else {
                                        // Erstelle neuen Termin
                                        $eventData['id'] = generateUniqueId();
                                        $calendarEvents[] = $eventData;
                                    }
                                    
                                    // Speichere Kalender
                                    $jsonData = json_encode($calendarEvents, JSON_PRETTY_PRINT);
                                    file_put_contents('../data/calendar.json', $jsonData);
                                    
                                } else if ($status === 'completed') {
                                    $case['verdict'] = $judgment;
                                    
                                    // Markiere den Fall als abgeschlossen
                                    $case['is_closed'] = true;
                                    $case['closed_date'] = date('Y-m-d H:i:s');
                                    $case['closed_reason'] = 'Urteil ausgesprochen';
                                }
                                
                                // Notiz zum Fall hinzufügen
                                $statusGerman = mapStatusToGerman($status);
                                $statusNote = "Status aktualisiert auf: " . $statusGerman . " - " . date('d.m.Y H:i:s') . " von " . $username;
                                $case['notes'] = ($case['notes'] ? $statusNote . "\n\n" . $case['notes'] : $statusNote);
                                
                                updateRecord('cases.json', $case['id'], $case);
                            }
                            
                            $message = 'Klageschrift wurde erfolgreich aktualisiert.';
                        } else {
                            $error = 'Fehler beim Aktualisieren der Klageschrift.';
                        }
                    }
                }
            }
        }
        // Löschen einer Klageschrift
        else if ($action === 'delete' && isset($_POST['indictment_id'])) {
            // Nur Administratoren können Klageschriften löschen
            if ($role !== 'Administrator') {
                $error = 'Sie haben keine Berechtigung, Klageschriften zu löschen.';
            } else {
                $indictmentId = sanitize($_POST['indictment_id']);
                
                if (deleteRecord('indictments.json', $indictmentId)) {
                    $message = 'Klageschrift erfolgreich gelöscht.';
                } else {
                    $error = 'Fehler beim Löschen der Klageschrift.';
                }
            }
        }
    }
}

// Lade Fälle und Klageschriften
$cases = getJsonData('cases.json');
$indictments = getJsonData('indictments.json');

// Bestimme Benutzerberechtigungen für die Anzeige
$isJudge = checkUserHasRoleType($role, 'judge');
$isLeadership = checkUserHasRoleType($role, 'leadership');
$isProsecutor = checkUserHasRoleType($role, 'prosecutor');

// Filtere Fälle basierend auf der Benutzerrolle
if ($isProsecutor && !$isJudge && !$isLeadership) {
    // Staatsanwälte sehen nur ihre eigenen Fälle
    $cases = array_filter($cases, function($case) use ($username) {
        return isset($case['prosecutor']) && $case['prosecutor'] === $username;
    });
}

// Richter und Führungskräfte sehen alle Klageschriften, Staatsanwälte nur ihre eigenen
if (!$isJudge && !$isLeadership) {
    $indictments = array_filter($indictments, function($indictment) use ($username) {
        return isset($indictment['prosecutor_name']) && $indictment['prosecutor_name'] === $username;
    });
}

// Sortiere Fälle nach Angeklagtenname
usort($cases, function($a, $b) {
    return strcmp($a['defendant'] ?? '', $b['defendant'] ?? '');
});

// Ausgewählten Fall und seine Klageschrift erhalten
$selectedCase = null;
$selectedIndictment = null;
$viewingIndictmentDetails = false;

if (isset($_GET['case_id'])) {
    $caseId = $_GET['case_id'];
    $selectedCase = findById('cases.json', $caseId);
    
    // Finde Klageschrift für diesen Fall
    foreach ($indictments as $indictment) {
        if ($indictment['case_id'] === $caseId) {
            $selectedIndictment = $indictment;
            break;
        }
    }
} else if (isset($_GET['id']) && isset($_GET['view'])) {
    // Details oder Bearbeiten für spezifische Klageschrift anzeigen
    $indictmentId = $_GET['id'];
    $selectedIndictment = findById('indictments.json', $indictmentId);
    
    // Festlegen, ob wir im Detail- oder Bearbeitungsmodus sind
    $viewingIndictmentDetails = isset($_GET['view']) && $_GET['view'] === 'detail';
    $editingIndictment = isset($_GET['view']) && $_GET['view'] === 'edit';
    
    if ($selectedIndictment) {
        $selectedCase = findById('cases.json', $selectedIndictment['case_id']);
        if (!$editingIndictment) {
            $viewingIndictmentDetails = true;
        }
    }
}

// Kategorisiere Klageschriften nach Status
$pendingIndictments = [];
$acceptedIndictments = [];
$scheduledIndictments = [];
$rejectedIndictments = [];
$completedIndictments = [];
$otherIndictments = [];

foreach ($indictments as $indictment) {
    $case = findById('cases.json', $indictment['case_id']);
    if ($case) {
        // Diese Filterung ist bereits oben implementiert und nicht mehr nötig
        
        $indictment['case'] = $case;
        
        // Nach Status kategorisieren
        switch($indictment['status']) {
            case 'pending':
                $pendingIndictments[] = $indictment;
                break;
            case 'accepted':
                $acceptedIndictments[] = $indictment;
                break;
            case 'scheduled':
                $scheduledIndictments[] = $indictment;
                break;
            case 'rejected':
                $rejectedIndictments[] = $indictment;
                break;
            case 'completed':
                $completedIndictments[] = $indictment;
                break;
            default:
                $otherIndictments[] = $indictment;
                break;
        }
    }
}

// Sortierungsfunktion nach Datum (neueste zuerst)
$sortByDate = function($a, $b) {
    $dateA = strtotime($a['process_date'] ?? ($a['decision_date'] ?? $a['date_created']));
    $dateB = strtotime($b['process_date'] ?? ($b['decision_date'] ?? $b['date_created']));
    return $dateB - $dateA;
};

// Sortiere alle Kategorien
usort($pendingIndictments, $sortByDate);
usort($acceptedIndictments, $sortByDate);
usort($scheduledIndictments, $sortByDate);
usort($rejectedIndictments, $sortByDate);
usort($completedIndictments, $sortByDate);
usort($otherIndictments, $sortByDate);

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Klageschriften</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="row">
                <?php if ($isJudge || $isLeadership || ($isProsecutor && !empty($pendingIndictments))): ?>
                <!-- Alle Rollen sehen ihre relevanten ausstehenden Klageschriften -->
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">Ausstehende Klageschriften</h4>
                        </div>
                        <div class="card-body">
                            <?php if (count($pendingIndictments) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Aktenzeichen</th>
                                                <th>Angeklagter</th>
                                                <th>Anklage</th>
                                                <th>Staatsanwalt</th>
                                                <th>Einreichungsdatum</th>
                                                <th>Aktionen</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingIndictments as $indictment): ?>
                                                <tr>
                                                    <td><a href="cases.php?id=<?php echo $indictment['case_id']; ?>">#<?php echo substr($indictment['case_id'], 0, 8); ?></a></td>
                                                    <td><?php echo htmlspecialchars($indictment['case']['defendant'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($indictment['case']['charge'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($indictment['prosecutor_name']); ?></td>
                                                    <td><?php echo date('d.m.Y', strtotime($indictment['date_created'])); ?></td>
                                                    <td>
                                                        <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=detail" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-eye"></i> Prüfen
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Keine ausstehenden Klageschriften gefunden.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($editingIndictment && $selectedIndictment): ?>
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-white">
                                <h4 class="mb-0">Klageschrift bearbeiten</h4>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h5>Falldetails</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Angeklagter:</strong> <?php echo htmlspecialchars($selectedCase['defendant'] ?? ''); ?></p>
                                            <p><strong>Anklage:</strong> <?php echo htmlspecialchars($selectedCase['charge'] ?? ''); ?></p>
                                            <p><strong>Vorfalldatum:</strong> <?php echo formatDate($selectedCase['incident_date'] ?? '', 'd.m.Y'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Bezirk:</strong> <?php echo htmlspecialchars($selectedCase['district'] ?? ''); ?></p>
                                            <p><strong>Staatsanwalt:</strong> <?php echo htmlspecialchars($selectedCase['prosecutor'] ?? ''); ?></p>
                                            <p><strong>Status:</strong> <?php echo htmlspecialchars(mapStatusToGerman($selectedCase['status'] ?? '')); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5>Inhalt der Klageschrift</h5>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <?php echo nl2br(htmlspecialchars($selectedIndictment['content'])); ?>
                                        </div>
                                    </div>
                                    <p class="text-muted mt-2">
                                        Eingereicht von <?php echo htmlspecialchars($selectedIndictment['prosecutor_name']); ?> 
                                        am <?php echo date('d.m.Y', strtotime($selectedIndictment['date_created'])); ?>
                                    </p>
                                </div>
                                
                                <!-- Formular zum Hinzufügen/Bearbeiten eines Urteils -->
                                <div class="mb-4">
                                    <h5>Urteil <?php echo !empty($selectedIndictment['verdict']) ? 'bearbeiten' : 'hinzufügen'; ?></h5>
                                    <form method="post" action="indictments.php">
                                        <input type="hidden" name="indictment_id" value="<?php echo $selectedIndictment['id']; ?>">
                                        <input type="hidden" name="action" value="update_accepted_indictment">
                                        
                                        <div class="form-group">
                                            <label for="verdict">Urteilstext</label>
                                            <textarea class="form-control" id="verdict" name="verdict" rows="5" required><?php echo htmlspecialchars($selectedIndictment['verdict'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="verdict_date">Datum des Urteils</label>
                                            <input type="date" class="form-control" id="verdict_date" name="verdict_date" 
                                                value="<?php echo htmlspecialchars($selectedIndictment['verdict_date'] ?? date('Y-m-d')); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="status">Status nach Urteil</label>
                                            <select class="form-control" id="status" name="status" required>
                                                <option value="completed" <?php echo ($selectedIndictment['status'] === 'completed') ? 'selected' : ''; ?>>
                                                    Abgeschlossen
                                                </option>
                                                <option value="appealed" <?php echo ($selectedIndictment['status'] === 'appealed') ? 'selected' : ''; ?>>
                                                    Berufung eingelegt
                                                </option>
                                                <option value="revision_requested" <?php echo ($selectedIndictment['status'] === 'revision_requested') ? 'selected' : ''; ?>>
                                                    Revision beantragt
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-primary">Speichern</button>
                                            <a href="indictments.php?id=<?php echo $selectedIndictment['id']; ?>&view=detail" class="btn btn-secondary">Abbrechen</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($viewingIndictmentDetails && $selectedIndictment): ?>
                    <div class="col-md-12">
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h4 class="mb-0">Klageschrift prüfen</h4>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h5>Falldetails</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Angeklagter:</strong> <?php echo htmlspecialchars($selectedCase['defendant'] ?? ''); ?></p>
                                            <p><strong>Anklage:</strong> <?php echo htmlspecialchars($selectedCase['charge'] ?? ''); ?></p>
                                            <p><strong>Vorfalldatum:</strong> <?php echo formatDate($selectedCase['incident_date'] ?? '', 'd.m.Y'); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Bezirk:</strong> <?php echo htmlspecialchars($selectedCase['district'] ?? ''); ?></p>
                                            <p><strong>Staatsanwalt:</strong> <?php echo htmlspecialchars($selectedCase['prosecutor'] ?? ''); ?></p>
                                            <p><strong>Status:</strong> <?php echo htmlspecialchars(mapStatusToGerman($selectedCase['status'] ?? '')); ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5>Inhalt der Klageschrift</h5>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <?php echo nl2br(htmlspecialchars($selectedIndictment['content'])); ?>
                                        </div>
                                    </div>
                                    <p class="text-muted mt-2">
                                        Eingereicht von <?php echo htmlspecialchars($selectedIndictment['prosecutor_name']); ?> 
                                        am <?php echo date('d.m.Y', strtotime($selectedIndictment['date_created'])); ?>
                                    </p>
                                </div>
                                
                                <?php if (($isJudge || $isLeadership) && $selectedIndictment['status'] === 'pending'): ?>
                                    <div class="mb-4">
                                        <h5>Klageschrift verarbeiten</h5>
                                        <form method="post" action="indictments.php">
                                            <input type="hidden" name="indictment_id" value="<?php echo $selectedIndictment['id']; ?>">
                                            <input type="hidden" name="action" value="process_indictment">
                                            
                                            <div class="form-group">
                                                <label>Entscheidung</label>
                                                <select name="status" class="form-control" required id="decision-select">
                                                    <option value="">-- Bitte wählen --</option>
                                                    <option value="accepted">Klageschrift annehmen</option>
                                                    <option value="rejected">Klageschrift ablehnen</option>
                                                    <option value="scheduled">Verhandlung terminieren</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group trial-date-group" style="display: none;">
                                                <label>Verhandlungstermin</label>
                                                <input type="datetime-local" name="trial_date" class="form-control" min="<?php echo date('Y-m-d\TH:i'); ?>">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label>Begründung / Anmerkungen</label>
                                                <textarea name="judgment" rows="3" class="form-control"></textarea>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-success">Entscheidung speichern</button>
                                        </form>
                                        
                                        <script>
                                            // Zeige/verstecke das Datum/Uhrzeit-Feld je nach Auswahl
                                            document.getElementById('decision-select').addEventListener('change', function() {
                                                var trialDateGroup = document.querySelector('.trial-date-group');
                                                if (this.value === 'scheduled') {
                                                    trialDateGroup.style.display = 'block';
                                                    trialDateGroup.querySelector('input').setAttribute('required', 'required');
                                                } else {
                                                    trialDateGroup.style.display = 'none';
                                                    trialDateGroup.querySelector('input').removeAttribute('required');
                                                }
                                            });
                                        </script>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($selectedIndictment['status'] !== 'pending'): ?>
                                    <div class="mb-4">
                                        <h5>Entscheidung</h5>
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <p>
                                                    <strong>Status:</strong> 
                                                    <?php 
                                                        $statusClass = '';
                                                        $statusText = mapStatusToGerman($selectedIndictment['status']);
                                                        
                                                        switch($selectedIndictment['status']) {
                                                            case 'accepted': $statusClass = 'success'; break;
                                                            case 'rejected': $statusClass = 'danger'; break;
                                                            case 'scheduled': $statusClass = 'primary'; break;
                                                            case 'completed': $statusClass = 'secondary'; break;
                                                            case 'appealed': $statusClass = 'warning'; break;
                                                            case 'revision_requested': $statusClass = 'info'; break;
                                                            default: $statusClass = 'info'; break;
                                                        }
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                </p>
                                                <?php if ($selectedIndictment['status'] === 'scheduled'): ?>
                                                    <p><strong>Verhandlungstermin:</strong> <?php echo formatDate($selectedIndictment['trial_date'], 'd.m.Y H:i'); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($selectedIndictment['judgment'])): ?>
                                                    <p><strong>Begründung / Anmerkungen:</strong></p>
                                                    <p><?php echo nl2br(htmlspecialchars($selectedIndictment['judgment'])); ?></p>
                                                <?php endif; ?>
                                                <p class="text-muted">
                                                    Bearbeitet von <?php echo htmlspecialchars($selectedIndictment['processor_name'] ?? ''); ?> 
                                                    am <?php echo date('d.m.Y', strtotime($selectedIndictment['process_date'] ?? $selectedIndictment['date_updated'] ?? $selectedIndictment['date_created'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($isLeadership): ?>
                                    <div class="mt-4">
                                        <form method="post" action="indictments.php" onsubmit="return confirm('Sind Sie sicher, dass Sie diese Klageschrift löschen möchten?');">
                                            <input type="hidden" name="indictment_id" value="<?php echo $selectedIndictment['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger">Klageschrift löschen</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Verfahrensmanagement-Bereich -->
                                <?php if (($isJudge || $isLeadership) && 
                                          ($selectedIndictment['status'] === 'accepted' || $selectedIndictment['status'] === 'scheduled')): ?>
                                <div class="mb-4">
                                    <h5>Verfahrensmanagement</h5>
                                    <div class="card border-secondary">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <?php if ($selectedIndictment['status'] === 'scheduled'): ?>
                                                        <div class="alert alert-info mb-0">
                                                            <strong>Gerichtstermin:</strong> <?php echo formatDate($selectedIndictment['trial_date'] ?? '', 'd.m.Y H:i'); ?> Uhr
                                                            <?php if (!empty($selectedIndictment['trial_notes'])): ?>
                                                                <p class="mt-2 mb-0"><strong>Anmerkungen:</strong> <?php echo htmlspecialchars($selectedIndictment['trial_notes']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div>
                                                    <?php if ($selectedIndictment['status'] === 'accepted'): ?>
                                                        <!-- Direkter Link zu dedizierter Seite statt Modal -->
                                                        <a href="schedule_court.php?id=<?php echo $selectedIndictment['id']; ?>" class="btn btn-primary">
                                                            <i class="fas fa-calendar-alt"></i> Gerichtsverhandlung terminieren
                                                        </a>
                                                    <?php elseif ($selectedIndictment['status'] === 'scheduled'): ?>
                                                        <!-- Direkter Link zu dedizierter Seite statt Modal -->
                                                        <a href="enter_verdict.php?id=<?php echo $selectedIndictment['id']; ?>" class="btn btn-success">
                                                            <i class="fas fa-gavel"></i> Urteil eintragen
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Urteilsanzeige wenn abgeschlossen -->
                                <?php if ($selectedIndictment['status'] === 'completed'): ?>
                                <div class="mb-4">
                                    <h5>Urteil</h5>
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <div class="alert alert-success mb-0">
                                                <p><strong>Urteil vom <?php echo formatDate($selectedIndictment['verdict_date'] ?? '', 'd.m.Y'); ?>:</strong></p>
                                                <p><?php echo nl2br(htmlspecialchars($selectedIndictment['verdict'] ?? 'Kein Urteilstext verfügbar')); ?></p>
                                                <?php if (!empty($selectedIndictment['verdict_by'])): ?>
                                                <p class="text-muted mt-3 mb-0">
                                                    <small>Eingetragen von: <?php echo htmlspecialchars($selectedIndictment['verdict_by_name'] ?? 'Unbekannt'); ?></small>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mt-4">
                                    <a href="indictments.php" class="btn btn-primary">Zurück zur Übersicht</a>
                                    <?php if (($isJudge || $isLeadership) && ($selectedIndictment['status'] === 'accepted' || $selectedIndictment['status'] === 'scheduled')): ?>
                                    <a href="indictments.php?id=<?php echo $selectedIndictment['id']; ?>&view=edit" class="btn btn-warning">
                                        <i class="fa fa-edit"></i> Bearbeiten
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Weitere Klageschriften nach Status gruppiert -->
                <?php if (!$viewingIndictmentDetails): ?>
                    <?php if (count($acceptedIndictments) > 0): ?>
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header bg-success text-white">
                                    <h4 class="mb-0">Angenommene Klageschriften</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Aktenzeichen</th>
                                                    <th>Angeklagter</th>
                                                    <th>Anklage</th>
                                                    <th>Staatsanwalt</th>
                                                    <th>Verarbeitungsdatum</th>
                                                    <th>Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($acceptedIndictments as $indictment): ?>
                                                    <tr>
                                                        <td><a href="cases.php?id=<?php echo $indictment['case_id']; ?>">#<?php echo substr($indictment['case_id'], 0, 8); ?></a></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['defendant'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['charge'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['prosecutor_name']); ?></td>
                                                        <td><?php echo date('d.m.Y', strtotime($indictment['process_date'] ?? $indictment['date_updated'])); ?></td>
                                                        <td>
                                                            <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=detail" class="btn btn-sm btn-primary">
                                                                <i class="fa fa-eye"></i> Details
                                                            </a>
                                                            <?php if (($isJudge || $isLeadership) && ($indictment['status'] === 'accepted' || $indictment['status'] === 'scheduled')): ?>
                                                            <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=edit" class="btn btn-sm btn-warning">
                                                                <i class="fa fa-edit"></i> Bearbeiten
                                                            </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($scheduledIndictments) > 0): ?>
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h4 class="mb-0">Terminierte Verhandlungen</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Aktenzeichen</th>
                                                    <th>Angeklagter</th>
                                                    <th>Anklage</th>
                                                    <th>Verhandlungstermin</th>
                                                    <th>Richter</th>
                                                    <th>Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($scheduledIndictments as $indictment): ?>
                                                    <tr>
                                                        <td><a href="cases.php?id=<?php echo $indictment['case_id']; ?>">#<?php echo substr($indictment['case_id'], 0, 8); ?></a></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['defendant'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['charge'] ?? ''); ?></td>
                                                        <td><?php echo formatDate($indictment['trial_date'], 'd.m.Y H:i'); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['processor_name']); ?></td>
                                                        <td>
                                                            <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=detail" class="btn btn-sm btn-primary">
                                                                <i class="fa fa-eye"></i> Details
                                                            </a>
                                                            <?php if (($isJudge || $isLeadership) && ($indictment['status'] === 'accepted' || $indictment['status'] === 'scheduled')): ?>
                                                            <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=edit" class="btn btn-sm btn-warning">
                                                                <i class="fa fa-edit"></i> Bearbeiten
                                                            </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($completedIndictments) > 0): ?>
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header bg-secondary text-white">
                                    <h4 class="mb-0">Abgeschlossene Fälle</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Aktenzeichen</th>
                                                    <th>Angeklagter</th>
                                                    <th>Anklage</th>
                                                    <th>Abschlussdatum</th>
                                                    <th>Richter</th>
                                                    <th>Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($completedIndictments as $indictment): ?>
                                                    <tr>
                                                        <td><a href="cases.php?id=<?php echo $indictment['case_id']; ?>">#<?php echo substr($indictment['case_id'], 0, 8); ?></a></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['defendant'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['charge'] ?? ''); ?></td>
                                                        <td><?php echo formatDate($indictment['completed_date'] ?? $indictment['process_date'], 'd.m.Y'); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['processor_name']); ?></td>
                                                        <td>
                                                            <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=detail" class="btn btn-sm btn-primary">
                                                                <i class="fa fa-eye"></i> Details
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Modal für Gerichtsverhandlung terminieren -->
                    <div class="modal fade" id="scheduleCourtModal" tabindex="-1" role="dialog" aria-labelledby="scheduleCourtModalLabel" aria-hidden="true" data-backdrop="static">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title" id="scheduleCourtModalLabel">Gerichtsverhandlung terminieren</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form method="post" action="indictments.php">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="schedule_court_date">
                                        <input type="hidden" name="indictment_id" value="<?php echo $selectedIndictment['id'] ?? ''; ?>">
                                        
                                        <div class="form-group">
                                            <label for="trial_date"><strong>Datum der Verhandlung</strong></label>
                                            <input type="date" class="form-control" id="trial_date" name="trial_date" 
                                                value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                            <small class="form-text text-muted">Bitte wählen Sie das Datum der Gerichtsverhandlung.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="trial_time"><strong>Uhrzeit der Verhandlung</strong></label>
                                            <input type="time" class="form-control" id="trial_time" name="trial_time" 
                                                value="10:00" required>
                                            <small class="form-text text-muted">Bitte geben Sie die Uhrzeit der Gerichtsverhandlung an.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="trial_notes"><strong>Anmerkungen zur Verhandlung</strong></label>
                                            <textarea class="form-control" id="trial_notes" name="trial_notes" rows="3"></textarea>
                                            <small class="form-text text-muted">Optionale Anmerkungen zur geplanten Verhandlung.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                        <button type="submit" class="btn btn-primary">Termin festlegen</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Modal für Urteil eintragen -->
                    <div class="modal fade" id="enterVerdictModal" tabindex="-1" role="dialog" aria-labelledby="enterVerdictModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title" id="enterVerdictModalLabel">Urteil eintragen</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Schließen">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <form method="post" action="indictments.php">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="enter_verdict">
                                        <input type="hidden" name="indictment_id" value="<?php echo $selectedIndictment['id'] ?? ''; ?>">
                                        
                                        <div class="form-group">
                                            <label for="verdict"><strong>Urteilstext</strong></label>
                                            <textarea class="form-control" id="verdict" name="verdict" rows="6" required></textarea>
                                            <small class="form-text text-muted">Bitte geben Sie den vollständigen Urteilstext ein.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="verdict_date"><strong>Datum des Urteils</strong></label>
                                            <input type="date" class="form-control" id="verdict_date" name="verdict_date" 
                                                value="<?php echo date('Y-m-d'); ?>" required>
                                            <small class="form-text text-muted">Standardmäßig auf heute gesetzt.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                                        <button type="submit" class="btn btn-success">Urteil speichern</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (count($rejectedIndictments) > 0): ?>
                        <div class="col-md-12">
                            <div class="card mb-4">
                                <div class="card-header bg-danger text-white">
                                    <h4 class="mb-0">Abgelehnte Klageschriften</h4>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Aktenzeichen</th>
                                                    <th>Angeklagter</th>
                                                    <th>Anklage</th>
                                                    <th>Ablehnungsdatum</th>
                                                    <th>Richter</th>
                                                    <th>Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rejectedIndictments as $indictment): ?>
                                                    <tr>
                                                        <td><a href="cases.php?id=<?php echo $indictment['case_id']; ?>">#<?php echo substr($indictment['case_id'], 0, 8); ?></a></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['defendant'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['case']['charge'] ?? ''); ?></td>
                                                        <td><?php echo formatDate($indictment['rejection_date'] ?? $indictment['process_date'], 'd.m.Y'); ?></td>
                                                        <td><?php echo htmlspecialchars($indictment['processor_name']); ?></td>
                                                        <td>
                                                            <a href="indictments.php?id=<?php echo $indictment['id']; ?>&view=detail" class="btn btn-sm btn-primary">
                                                                <i class="fa fa-eye"></i> Details
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
// Warten bis das Dokument vollständig geladen ist
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded-Event für indictments.php wurde ausgelöst');
    
    // Zeige/verstecke Verhandlungstermin-Feld je nach ausgewähltem Status
    var decisionSelect = document.getElementById('decision-select');
    if (decisionSelect) {
        decisionSelect.addEventListener('change', function() {
            var trialDateGroup = document.querySelector('.trial-date-group');
            if (this.value === 'scheduled') {
                trialDateGroup.style.display = 'block';
                trialDateGroup.querySelector('input').required = true;
            } else {
                trialDateGroup.style.display = 'none';
                trialDateGroup.querySelector('input').required = false;
            }
        });
    }
    
    // Direktes Event-Handling für die Modals, so einfach wie möglich
    var scheduleCourtBtn = document.getElementById('scheduleCourtBtn');
    var enterVerdictBtn = document.getElementById('enterVerdictBtn');
    
    // Modal-Elemente suchen
    var scheduleCourtModal = document.getElementById('scheduleCourtModal');
    var enterVerdictModal = document.getElementById('enterVerdictModal');
    
    console.log('Modal-Elemente: ', {
        scheduleCourtBtn: scheduleCourtBtn ? 'gefunden' : 'nicht gefunden',
        enterVerdictBtn: enterVerdictBtn ? 'gefunden' : 'nicht gefunden',
        scheduleCourtModal: scheduleCourtModal ? 'gefunden' : 'nicht gefunden',
        enterVerdictModal: enterVerdictModal ? 'gefunden' : 'nicht gefunden'
    });
    
    // Button-Ereignisse
    if (scheduleCourtBtn && scheduleCourtModal) {
        scheduleCourtBtn.onclick = function(e) {
            e.preventDefault();
            jQuery('#scheduleCourtModal').modal('show');
            console.log('Gerichtsverhandlung terminieren Button wurde geklickt');
            return false;
        };
    }
    
    if (enterVerdictBtn && enterVerdictModal) {
        enterVerdictBtn.onclick = function(e) {
            e.preventDefault();
            jQuery('#enterVerdictModal').modal('show');
            console.log('Urteil eintragen Button wurde geklickt');
            return false;
        };
    }
    
    // Fülle Default-Werte für die Formulare aus
    var trialDateInput = document.getElementById('trial_date');
    if (trialDateInput && !trialDateInput.value) {
        var nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 7);
        trialDateInput.value = nextWeek.toISOString().split('T')[0];
    }
    
    var verdictDateInput = document.getElementById('verdict_date');
    if (verdictDateInput && !verdictDateInput.value) {
        var today = new Date().toISOString().split('T')[0];
        verdictDateInput.value = today;
    }
});
</script>

<?php include '../includes/footer.php'; ?>