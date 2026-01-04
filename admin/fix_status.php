<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Ensure only admins can access this page
if (!isAdminSession()) {
    header('Location: ../dashboard.php');
    exit;
}

$message = '';
$error = '';

// Klageschriften korrigieren
$indictments = getJsonData('indictments.json');
$updated = false;

// Überprüfe und korrigiere die Status-Werte
foreach ($indictments as $key => $indictment) {
    $oldStatus = $indictment['status'];
    // Konvertiere deutsche Status-Werte in englische
    if ($oldStatus === 'ausstehend') {
        $indictments[$key]['status'] = 'pending';
        $updated = true;
    } elseif ($oldStatus === 'angenommen') {
        $indictments[$key]['status'] = 'accepted';
        $updated = true;
    } elseif ($oldStatus === 'abgelehnt') {
        $indictments[$key]['status'] = 'rejected';
        $updated = true;
    } elseif ($oldStatus === 'terminiert') {
        $indictments[$key]['status'] = 'scheduled';
        $updated = true;
    } elseif ($oldStatus === 'abgeschlossen') {
        $indictments[$key]['status'] = 'completed';
        $updated = true;
    }
    
    // Aktualisiere auch die decision_history, falls vorhanden
    if (isset($indictment['decision_history']) && !empty($indictment['decision_history'])) {
        foreach ($indictment['decision_history'] as $histIndex => $historyItem) {
            $oldHistStatus = $historyItem['status'];
            if ($oldHistStatus === 'ausstehend') {
                $indictments[$key]['decision_history'][$histIndex]['status'] = 'pending';
                $updated = true;
            } elseif ($oldHistStatus === 'angenommen') {
                $indictments[$key]['decision_history'][$histIndex]['status'] = 'accepted';
                $updated = true;
            } elseif ($oldHistStatus === 'abgelehnt') {
                $indictments[$key]['decision_history'][$histIndex]['status'] = 'rejected';
                $updated = true;
            } elseif ($oldHistStatus === 'terminiert') {
                $indictments[$key]['decision_history'][$histIndex]['status'] = 'scheduled';
                $updated = true;
            } elseif ($oldHistStatus === 'abgeschlossen') {
                $indictments[$key]['decision_history'][$histIndex]['status'] = 'completed';
                $updated = true;
            }
        }
    }
}

// Speichere die aktualisierten Klageschriften
if ($updated) {
    $jsonData = json_encode($indictments, JSON_PRETTY_PRINT);
    $filePath = '../data/indictments.json'; // Korrekter relativer Pfad von admin/ aus
    if (file_put_contents($filePath, $jsonData)) {
        $message .= "Die Status-Werte in der Klageschrift-Datenbank wurden erfolgreich standardisiert.<br>";
    } else {
        $error .= "Fehler beim Aktualisieren der Klageschrift-Datenbank.<br>";
    }
} else {
    $message .= "Keine Änderungen an den Klageschriften notwendig.<br>";
}

// Fälle korrigieren
$cases = getJsonData('cases.json');
$casesUpdated = false;

// Überprüfe und korrigiere die Status-Werte in den Fällen
foreach ($cases as $key => $case) {
    $oldStatus = $case['status'];
    // Konvertiere deutsche Status-Werte in englische
    if ($oldStatus === 'Offen') {
        $cases[$key]['status'] = 'open';
        $casesUpdated = true;
    } elseif ($oldStatus === 'In Bearbeitung') {
        $cases[$key]['status'] = 'in_progress';
        $casesUpdated = true;
    } elseif ($oldStatus === 'Klageschrift eingereicht') {
        $cases[$key]['status'] = 'pending';
        $casesUpdated = true;
    } elseif ($oldStatus === 'Klage angenommen') {
        $cases[$key]['status'] = 'accepted';
        $casesUpdated = true;
    } elseif ($oldStatus === 'Terminiert') {
        $cases[$key]['status'] = 'scheduled';
        $casesUpdated = true;
    } elseif ($oldStatus === 'Abgeschlossen') {
        $cases[$key]['status'] = 'completed';
        $casesUpdated = true;
    } elseif ($oldStatus === 'Abgelehnt') {
        $cases[$key]['status'] = 'rejected';
        $casesUpdated = true;
    } elseif ($oldStatus === 'Eingestellt') {
        $cases[$key]['status'] = 'dismissed';
        $casesUpdated = true;
    }
}

// Speichere die aktualisierten Fälle
if ($casesUpdated) {
    $jsonData = json_encode($cases, JSON_PRETTY_PRINT);
    $filePath = '../data/cases.json'; // Korrekter relativer Pfad von admin/ aus
    if (file_put_contents($filePath, $jsonData)) {
        $message .= "Die Status-Werte in der Fall-Datenbank wurden erfolgreich standardisiert.";
    } else {
        $error .= "Fehler beim Aktualisieren der Fall-Datenbank.";
    }
} else {
    $message .= "Keine Änderungen an den Fällen notwendig.";
}

// Leite zurück zur Datenbank-Verwaltung
$_SESSION['admin_message'] = $message;
$_SESSION['admin_error'] = $error;
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Status-Werte korrigieren</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h4>Daten wurden korrigiert</h4>
                </div>
                <div class="card-body">
                    <p>Die Status-Werte in der Datenbank wurden auf standardisierte englische Werte korrigiert.</p>
                    <a href="../admin/database.php" class="btn btn-primary">Zurück zur Datenbank-Verwaltung</a>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/footer.php'; ?>