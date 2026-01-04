<?php
/**
 * Datenbank-Reset-Skript
 * Dieses Skript setzt die Datenbank vollständig zurück und initialisiert sie mit den Standarddaten
 */

// Warnhinweis und Bestätigung überspringen, da wir direkt in Replit ausführen
$confirm = true;

// Prüfen, ob die Aktion bestätigt wurde
if (!$confirm) {
    echo "Aktion abgebrochen.\n";
    exit(1);
}

echo "=================================================\n";
echo "Datenbank-Reset wird durchgeführt...\n";
echo "=================================================\n\n";

// Pfad zum data-Verzeichnis
$dataDir = __DIR__ . '/data';

// Stelle sicher, dass das data-Verzeichnis existiert
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// 1. Aktuelle Daten sichern in backups/
$backupDir = __DIR__ . '/backups/' . date('Y-m-d_H-i-s');
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Kopiere alle vorhandenen JSON-Dateien ins Backup-Verzeichnis
$jsonFiles = glob($dataDir . '/*.json');
foreach ($jsonFiles as $file) {
    $filename = basename($file);
    copy($file, $backupDir . '/' . $filename);
    echo "Datei gesichert: $filename\n";
}

// 2. JSON-Dateien zurücksetzen
// Lösche alle vorhandenen JSON-Dateien im data-Verzeichnis
foreach ($jsonFiles as $file) {
    unlink($file);
    echo "Datei gelöscht: " . basename($file) . "\n";
}

// 3. Standarddaten erstellen

// Rollen erstellen
$roles = [
    [
        'id' => 'chief_justice',
        'name' => 'Chief Justice',
        'description' => 'Oberster Richter und Systemadministrator',
        'level' => 1,
        'department' => 'Leadership',
        'permissions' => null
    ],
    [
        'id' => 'senior_associate_justice',
        'name' => 'Senior Associate Justice',
        'description' => 'Stellvertretender oberster Richter',
        'level' => 2,
        'department' => 'Leadership',
        'permissions' => null
    ],
    [
        'id' => 'attorney_general',
        'name' => 'Attorney General',
        'description' => 'Leiter der Staatsanwaltschaft',
        'level' => 2,
        'department' => 'Leadership',
        'permissions' => null
    ],
    [
        'id' => 'district_court_judge',
        'name' => 'District Court Judge',
        'description' => 'Bezirksrichter',
        'level' => 3,
        'department' => 'Judicial',
        'permissions' => null
    ],
    [
        'id' => 'judge',
        'name' => 'Judge',
        'description' => 'Richter',
        'level' => 4,
        'department' => 'Judicial',
        'permissions' => null
    ],
    [
        'id' => 'magistrate',
        'name' => 'Magistrate',
        'description' => 'Magistratsrichter',
        'level' => 5,
        'department' => 'Judicial',
        'permissions' => null
    ],
    [
        'id' => 'junior_magistrate',
        'name' => 'Junior Magistrate',
        'description' => 'Magistratsrichter in Ausbildung',
        'level' => 6,
        'department' => 'Judicial',
        'permissions' => null
    ],
    [
        'id' => 'district_attorney',
        'name' => 'District Attorney',
        'description' => 'Bezirksstaatsanwalt',
        'level' => 3,
        'department' => 'Prosecution',
        'permissions' => null
    ],
    [
        'id' => 'senior_prosecutor',
        'name' => 'Senior Prosecutor',
        'description' => 'Leitender Staatsanwalt',
        'level' => 4,
        'department' => 'Prosecution',
        'permissions' => null
    ],
    [
        'id' => 'prosecutor',
        'name' => 'Prosecutor',
        'description' => 'Staatsanwalt',
        'level' => 5,
        'department' => 'Prosecution',
        'permissions' => null
    ],
    [
        'id' => 'junior_prosecutor',
        'name' => 'Junior Prosecutor',
        'description' => 'Staatsanwalt in Ausbildung',
        'level' => 6,
        'department' => 'Prosecution',
        'permissions' => null
    ],
    [
        'id' => 'director',
        'name' => 'Director',
        'description' => 'Direktor des U.S. Marshal Service',
        'level' => 3,
        'department' => 'Marshal Service',
        'permissions' => null
    ],
    [
        'id' => 'commander',
        'name' => 'Commander',
        'description' => 'Kommandant des U.S. Marshal Service',
        'level' => 4,
        'department' => 'Marshal Service',
        'permissions' => null
    ],
    [
        'id' => 'senior_deputy',
        'name' => 'Senior Deputy',
        'description' => 'Leitender Deputy Marshal',
        'level' => 5,
        'department' => 'Marshal Service',
        'permissions' => null
    ],
    [
        'id' => 'deputy',
        'name' => 'Deputy',
        'description' => 'Deputy Marshal',
        'level' => 6,
        'department' => 'Marshal Service',
        'permissions' => null
    ],
    [
        'id' => 'junior_deputy',
        'name' => 'Junior Deputy',
        'description' => 'Junior Deputy Marshal',
        'level' => 7,
        'department' => 'Marshal Service',
        'permissions' => null
    ],
    [
        'id' => 'trainee',
        'name' => 'Trainee',
        'description' => 'Marshal Service Trainee',
        'level' => 8,
        'department' => 'Marshal Service',
        'permissions' => null
    ],
    [
        'id' => 'president',
        'name' => 'President',
        'description' => 'Präsident der Vereinigten Staaten',
        'level' => 1,
        'department' => 'External',
        'permissions' => null
    ],
    [
        'id' => 'secretary',
        'name' => 'Secretary',
        'description' => 'Staatssekretär',
        'level' => 2,
        'department' => 'External',
        'permissions' => null
    ],
    [
        'id' => 'army',
        'name' => 'Army',
        'description' => 'Militär',
        'level' => 3,
        'department' => 'External',
        'permissions' => null
    ],
    [
        'id' => 'sheriff',
        'name' => 'Sheriff',
        'description' => 'Sheriff',
        'level' => 3,
        'department' => 'External',
        'permissions' => null
    ],
    [
        'id' => 'administrative_assistant',
        'name' => 'Administrative Assistant',
        'description' => 'Verwaltungsassistent',
        'level' => 5,
        'department' => 'Administration',
        'permissions' => null
    ],
    [
        'id' => 'student',
        'name' => 'Student',
        'description' => 'Student/Praktikant',
        'level' => 9,
        'department' => 'Prosecution',
        'permissions' => null
    ],
    [
        'id' => 'administrator',
        'name' => 'Administrator',
        'description' => 'Systemadministrator (technisch)',
        'level' => 1,
        'department' => 'Administration',
        'permissions' => null
    ]
];

// Benutzer erstellen
$users = [
    [
        'id' => 'admin',
        'username' => 'OConnor',
        'password' => password_hash('admin', PASSWORD_DEFAULT),
        'role' => 'Chief Justice',
        'roles' => ['Chief Justice', 'Administrator'],
        'email' => 'admin@justice.gov',
        'first_name' => 'Sarah',
        'last_name' => 'O\'Connor',
        'is_active' => true,
        'date_created' => date('Y-m-d H:i:s'),
        'last_login' => null,
        'preferences' => [
            'theme' => 'light',
            'language' => 'de'
        ]
    ],
    [
        'id' => 'jpierce',
        'username' => 'JPierce',
        'password' => password_hash('password', PASSWORD_DEFAULT),
        'role' => 'Judge',
        'roles' => ['Judge'],
        'email' => 'jpierce@justice.gov',
        'first_name' => 'John',
        'last_name' => 'Pierce',
        'is_active' => true,
        'date_created' => date('Y-m-d H:i:s'),
        'last_login' => null,
        'preferences' => [
            'theme' => 'light',
            'language' => 'de'
        ]
    ],
    [
        'id' => 'mross',
        'username' => 'MRoss',
        'password' => password_hash('password', PASSWORD_DEFAULT),
        'role' => 'Prosecutor',
        'roles' => ['Prosecutor'],
        'email' => 'mross@justice.gov',
        'first_name' => 'Mary',
        'last_name' => 'Ross',
        'is_active' => true,
        'date_created' => date('Y-m-d H:i:s'),
        'last_login' => null,
        'preferences' => [
            'theme' => 'light',
            'language' => 'de'
        ]
    ]
];

// Fallakten erstellen
$cases = [
    [
        'id' => '001-2025',
        'title' => 'Staatsanwaltschaft vs. John Doe',
        'case_number' => 'CR-001-2025',
        'description' => 'Anklage wegen schweren Raubs',
        'status' => 'aktiv',
        'priority' => 'hoch',
        'created_by' => 'admin',
        'assigned_to' => 'mross',
        'date_created' => date('Y-m-d H:i:s', strtotime('-10 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'date_closed' => null,
        'tags' => ['Raub', 'Gewalt', 'Wiederholungstäter'],
        'related_cases' => [],
        'court_date' => date('Y-m-d H:i:s', strtotime('+14 days')),
        'court_location' => 'Saal 3, Hauptgebäude',
        'prosecutor' => 'mross',
        'defense_attorney' => 'Michael Jones',
        'judge' => 'jpierce',
        'defendant' => 'John Doe',
        'charge' => 'Schwerer Raub gemäß §249 StGB',
        'notes' => 'Der Angeklagte hat bereits mehrere Vorstrafen wegen ähnlicher Delikte.',
        'category' => 'Strafrecht'
    ],
    [
        'id' => '002-2025',
        'title' => 'Staatsanwaltschaft vs. Jane Smith',
        'case_number' => 'CR-002-2025',
        'description' => 'Anklage wegen Betrug',
        'status' => 'aktiv',
        'priority' => 'mittel',
        'created_by' => 'mross',
        'assigned_to' => 'mross',
        'date_created' => date('Y-m-d H:i:s', strtotime('-15 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'date_closed' => null,
        'tags' => ['Betrug', 'Finanzen'],
        'related_cases' => [],
        'court_date' => date('Y-m-d H:i:s', strtotime('+21 days')),
        'court_location' => 'Saal 2, Hauptgebäude',
        'prosecutor' => 'mross',
        'defense_attorney' => 'Sarah Williams',
        'judge' => 'jpierce',
        'defendant' => 'Jane Smith',
        'charge' => 'Betrug gemäß §263 StGB',
        'notes' => 'Die Angeklagte hat zahlreiche Personen um insgesamt über 100.000€ betrogen.',
        'category' => 'Strafrecht'
    ],
    [
        'id' => '003-2025',
        'title' => 'Staatsanwaltschaft vs. Robert Brown',
        'case_number' => 'CR-003-2025',
        'description' => 'Anklage wegen Körperverletzung',
        'status' => 'abgeschlossen',
        'priority' => 'niedrig',
        'created_by' => 'admin',
        'assigned_to' => 'mross',
        'date_created' => date('Y-m-d H:i:s', strtotime('-30 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'date_closed' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'tags' => ['Körperverletzung'],
        'related_cases' => [],
        'court_date' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'court_location' => 'Saal 1, Hauptgebäude',
        'prosecutor' => 'mross',
        'defense_attorney' => 'Thomas Martin',
        'judge' => 'jpierce',
        'defendant' => 'Robert Brown',
        'charge' => 'Einfache Körperverletzung gemäß §223 StGB',
        'notes' => 'Der Angeklagte wurde zu einer Geldstrafe von 90 Tagessätzen verurteilt.',
        'category' => 'Strafrecht'
    ],
    [
        'id' => '004-2025',
        'title' => 'Staatsanwaltschaft vs. Laura Johnson',
        'case_number' => 'CR-004-2025',
        'description' => 'Anklage wegen unerlaubten Besitzes von Betäubungsmitteln',
        'status' => 'aktiv',
        'priority' => 'mittel',
        'created_by' => 'mross',
        'assigned_to' => 'mross',
        'date_created' => date('Y-m-d H:i:s', strtotime('-8 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'date_closed' => null,
        'tags' => ['Betäubungsmittel', 'BtMG'],
        'related_cases' => [],
        'court_date' => null,
        'court_location' => null,
        'prosecutor' => 'mross',
        'defense_attorney' => 'Lisa Clark',
        'judge' => null,
        'defendant' => 'Laura Johnson',
        'charge' => 'Unerlaubter Besitz von Betäubungsmitteln gemäß §29 BtMG',
        'notes' => 'Der Angeklagten wurden 10g Kokain abgenommen.',
        'category' => 'Strafrecht'
    ]
];

// Klageschriften erstellen
$indictments = [
    [
        'id' => uniqid(),
        'case_id' => '001-2025',
        'title' => 'Anklageschrift gegen John Doe',
        'content' => "ANKLAGESCHRIFT\n\nDie Staatsanwaltschaft klagt den am 15.01.1985 in Berlin geborenen John Doe des schweren Raubs an.\n\nTatvorwurf:\nDer Angeschuldigte hat am 10.01.2025 gegen 22:30 Uhr in der Bankfiliale in der Hauptstraße 10 unter Vorhalt einer Schusswaffe 12.500€ erbeutet.",
        'prosecutor_id' => 'mross',
        'prosecutor_name' => 'Mary Ross',
        'status' => 'submitted', // submitted, accepted, rejected, scheduled, completed
        'date_created' => date('Y-m-d H:i:s', strtotime('-9 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-9 days')),
        'date_submitted' => date('Y-m-d H:i:s', strtotime('-9 days')),
        'process_date' => null,
        'processed_by' => null,
        'processed_by_name' => null,
        'rejection_reason' => null,
        'trial_date' => null,
        'trial_notes' => null,
        'verdict' => null,
        'verdict_date' => null,
        'verdict_by' => null,
        'verdict_by_name' => null
    ],
    [
        'id' => uniqid(),
        'case_id' => '002-2025',
        'title' => 'Anklageschrift gegen Jane Smith',
        'content' => "ANKLAGESCHRIFT\n\nDie Staatsanwaltschaft klagt die am 23.05.1978 in Hamburg geborene Jane Smith des Betrugs in mehreren Fällen an.\n\nTatvorwurf:\nDie Angeschuldigte hat im Zeitraum von Januar bis Oktober 2024 insgesamt 15 Personen durch betrügerische Anlageversprechen um insgesamt 127.500€ betrogen.",
        'prosecutor_id' => 'mross',
        'prosecutor_name' => 'Mary Ross',
        'status' => 'accepted',
        'date_created' => date('Y-m-d H:i:s', strtotime('-14 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-14 days')),
        'date_submitted' => date('Y-m-d H:i:s', strtotime('-14 days')),
        'process_date' => date('Y-m-d H:i:s', strtotime('-10 days')),
        'processed_by' => 'jpierce',
        'processed_by_name' => 'John Pierce',
        'rejection_reason' => null,
        'trial_date' => date('Y-m-d H:i:s', strtotime('+21 days')),
        'trial_notes' => 'Der Fall wird wegen seines Umfangs voraussichtlich mehrere Verhandlungstage erfordern.',
        'verdict' => null,
        'verdict_date' => null,
        'verdict_by' => null,
        'verdict_by_name' => null
    ],
    [
        'id' => uniqid(),
        'case_id' => '003-2025',
        'title' => 'Anklageschrift gegen Robert Brown',
        'content' => "ANKLAGESCHRIFT\n\nDie Staatsanwaltschaft klagt den am 05.08.1990 in München geborenen Robert Brown der Körperverletzung an.\n\nTatvorwurf:\nDer Angeschuldigte hat am 01.12.2024 gegen 01:15 Uhr in der Diskothek 'Nachtleben' dem Opfer Max Müller mit der Faust ins Gesicht geschlagen und ihm dadurch eine Nasenbeinfraktur zugefügt.",
        'prosecutor_id' => 'mross',
        'prosecutor_name' => 'Mary Ross',
        'status' => 'completed',
        'date_created' => date('Y-m-d H:i:s', strtotime('-29 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-29 days')),
        'date_submitted' => date('Y-m-d H:i:s', strtotime('-29 days')),
        'process_date' => date('Y-m-d H:i:s', strtotime('-25 days')),
        'processed_by' => 'jpierce',
        'processed_by_name' => 'John Pierce',
        'rejection_reason' => null,
        'trial_date' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'trial_notes' => null,
        'verdict' => "Der Angeklagte Robert Brown wird wegen Körperverletzung zu einer Geldstrafe von 90 Tagessätzen zu je 40€ verurteilt.\n\nDie Kosten des Verfahrens trägt der Angeklagte.",
        'verdict_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'verdict_by' => 'jpierce',
        'verdict_by_name' => 'John Pierce'
    ],
    [
        'id' => uniqid(),
        'case_id' => '004-2025',
        'title' => 'Anklageschrift gegen Laura Johnson',
        'content' => "ANKLAGESCHRIFT\n\nDie Staatsanwaltschaft klagt die am 17.03.1995 in Frankfurt geborene Laura Johnson des unerlaubten Besitzes von Betäubungsmitteln an.\n\nTatvorwurf:\nDie Angeschuldigte wurde am 22.01.2025 gegen 15:45 Uhr im Stadtpark mit 10g Kokain angetroffen, welches sie zum Eigenkonsum mit sich führte.",
        'prosecutor_id' => 'mross',
        'prosecutor_name' => 'Mary Ross',
        'status' => 'submitted',
        'date_created' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'date_updated' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'date_submitted' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'process_date' => null,
        'processed_by' => null,
        'processed_by_name' => null,
        'rejection_reason' => null,
        'trial_date' => null,
        'trial_notes' => null,
        'verdict' => null,
        'verdict_date' => null,
        'verdict_by' => null,
        'verdict_by_name' => null
    ]
];

// Speichern der erstellten Daten in JSON-Dateien
file_put_contents($dataDir . '/roles.json', json_encode($roles, JSON_PRETTY_PRINT));
file_put_contents($dataDir . '/users.json', json_encode($users, JSON_PRETTY_PRINT));
file_put_contents($dataDir . '/cases.json', json_encode($cases, JSON_PRETTY_PRINT));
file_put_contents($dataDir . '/indictments.json', json_encode($indictments, JSON_PRETTY_PRINT));

// Leere Equipment-Liste erstellen
file_put_contents($dataDir . '/equipment.json', json_encode([], JSON_PRETTY_PRINT));

// Leere Dokument-Liste erstellen
file_put_contents($dataDir . '/documents.json', json_encode([], JSON_PRETTY_PRINT));

// Leere Kalender-Liste erstellen
file_put_contents($dataDir . '/court_calendar.json', json_encode([], JSON_PRETTY_PRINT));

echo "\nDatenbank wurde erfolgreich zurückgesetzt und mit Standarddaten initialisiert.\n";
echo "Zugangsdaten für Administrator:\n";
echo "- Benutzername: OConnor\n";
echo "- Passwort: admin\n\n";

echo "=================================================\n";
echo "Prozess abgeschlossen\n";
echo "=================================================\n";

// Wenn im Browser ausgeführt, Link zurück zum Dashboard anzeigen
if (php_sapi_name() !== 'cli') {
    echo '<p><a href="index.php" class="btn btn-primary">Zurück zum Dashboard</a></p>';
}
?>