<?php
session_start();
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'autoload_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Hauptrolle
$roles = isset($_SESSION['roles']) ? $_SESSION['roles'] : [$role]; // Alle Rollen

// Get recent cases
$cases = getRecentCases(5);

// Get upcoming calendar events
$events = getUpcomingEvents(5);

// Get duty status
$dutyStatus = getUserDutyStatus($user_id);

// Get counts for dashboard
$caseCount = getCaseCount();
$defendantCount = getDefendantCount();
$openCaseCount = getOpenCaseCount();
$upcomingEventCount = getUpcomingEventCount();
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>

        <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Übersicht</h1>
                <div class="duty-status-indicator">
                    <?php if ($dutyStatus): ?>
                        <span class="badge badge-success">Im Dienst</span>
                    <?php else: ?>
                        <span class="badge badge-secondary">Nicht im Dienst</span>
                    <?php endif; ?>
                    <form method="post" action="modules/duty_log.php" class="d-inline ml-2">
                        <input type="hidden" name="action" value="<?php echo $dutyStatus ? 'off_duty' : 'on_duty'; ?>">
                        <button type="submit" class="btn btn-sm <?php echo $dutyStatus ? 'btn-outline-danger' : 'btn-outline-success'; ?>">
                            <?php echo $dutyStatus ? 'Dienst beenden' : 'Dienst beginnen'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <div class="welcome-message mb-4">
                <h3>Willkommen, <?php echo htmlspecialchars($username); ?> 
                    <small class="text-muted">
                        (<?php echo htmlspecialchars($role); ?>
                        <?php if (count($roles) > 1): ?>
                            <span class="badge bg-info" data-toggle="tooltip" data-placement="top" 
                                  title="<?php echo htmlspecialchars(implode(', ', $roles)); ?>">
                                +<?php echo count($roles)-1; ?> weitere
                            </span>
                        <?php endif; ?>)
                    </small>
                </h3>
                <p class="lead">Heute ist der <?php echo date('d. F Y'); ?></p>
            </div>

            <!-- Dashboard Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Alle Fälle</h5>
                            <p class="card-text display-4"><?php echo $caseCount; ?></p>
                            <a href="modules/cases.php" class="card-link">Alle Fälle anzeigen</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Offene Fälle</h5>
                            <p class="card-text display-4"><?php echo $openCaseCount; ?></p>
                            <a href="modules/cases.php?status=open" class="card-link">Offene Fälle anzeigen</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Angeklagte</h5>
                            <p class="card-text display-4"><?php echo $defendantCount; ?></p>
                            <a href="modules/defendants.php" class="card-link">Angeklagte anzeigen</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Anstehende Termine</h5>
                            <p class="card-text display-4"><?php echo $upcomingEventCount; ?></p>
                            <a href="modules/calendar.php" class="card-link">Kalender anzeigen</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Cases -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Neueste Fälle</h4>
                        </div>
                        <div class="card-body">
                            <?php if (count($cases) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($cases as $case): ?>
                                        <a href="modules/case_edit.php?id=<?php echo $case['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($case['defendant']); ?></h5>
                                                <small><?php echo htmlspecialchars($case['status']); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($case['charge']); ?></p>
                                            <small>Bezirk: <?php echo htmlspecialchars($case['district']); ?> | Staatsanwalt: <?php echo htmlspecialchars($case['prosecutor']); ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Keine Fälle gefunden.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="modules/cases.php" class="btn btn-outline-primary btn-sm">Alle Fälle anzeigen</a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h4>Anstehende Termine</h4>
                        </div>
                        <div class="card-body">
                            <?php if (count($events) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($events as $event): ?>
                                        <a href="modules/calendar.php?date=<?php echo $event['date']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                                <small><?php echo htmlspecialchars(date('d.m.Y', strtotime($event['date']))); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($event['description']); ?></p>
                                            <small>Uhrzeit: <?php echo htmlspecialchars($event['time']); ?></small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Keine anstehenden Termine.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="modules/calendar.php" class="btn btn-outline-primary btn-sm">Kalender anzeigen</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
