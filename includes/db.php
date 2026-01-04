<?php
/**
 * Database functions for both JSON data storage and PostgreSQL
 */

// Initialize PDO connection to PostgreSQL
function getPDO() {
    static $pdo = null;
    static $connectionAttempted = false;
    
    // Nur einmal pro Request versuchen, die Verbindung herzustellen
    if ($pdo === null && !$connectionAttempted) {
        $connectionAttempted = true;
        
        try {
            // Try DATABASE_URL first (Replit und andere Cloud-Umgebungen)
            $url = getenv('DATABASE_URL');
            
            if ($url) {
                // Verbindung mit DATABASE_URL herstellen
                $pdo = new PDO($url);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                error_log('Database connected using DATABASE_URL');
                return $pdo;
            }
            
            // Dann versuchen, mit einzelnen Parametern zu verbinden
            $host = getenv('PGHOST');
            $port = getenv('PGPORT');
            $dbname = getenv('PGDATABASE');
            $user = getenv('PGUSER');
            $password = getenv('PGPASSWORD');
            
            if ($host && $port && $dbname && $user && $password) {
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
                $pdo = new PDO($dsn, $user, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                error_log('Database connected using individual parameters');
                return $pdo;
            }
            
            // Keine Verbindungsparameter gefunden
            error_log('No database connection parameters found. Using JSON files for data storage.');
            return null;
            
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            // Fall back to JSON files if database connection fails
            return null;
        }
    }
    
    return $pdo;
}

/**
 * Check if the database is connected and available
 *
 * @return bool True if database is connected, false otherwise
 */
function isDatabaseConnected() {
    return getPDO() !== null;
}

/**
 * Find a record by ID in a JSON file or database
 * 
 * @param string $filename The JSON file to search in or table name in DB
 * @param string $id The ID to find
 * @return array|null The found record or null if not found
 */
function findById($filename, $id) {
    // Try database first if connected
    $pdo = getPDO();
    if ($pdo !== null && isTableMigratedToDatabase($filename)) {
        $tableName = getTableNameFromFilename($filename);
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM $tableName WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return $result;
            }
        } catch (Exception $e) {
            error_log("Database query error in findById: " . $e->getMessage());
            // Fall back to JSON
        }
    }
    
    // Fall back to JSON file
    $data = loadJsonData($filename);
    
    foreach ($data as $item) {
        if (isset($item['id']) && $item['id'] === $id) {
            return $item;
        }
    }
    
    return null;
}

/**
 * Helper function to check if table is migrated to database
 *
 * @param string $filename The JSON filename
 * @return bool True if table exists in database
 */
function isTableMigratedToDatabase($filename) {
    // Wenn keine Datenbankverbindung besteht, können wir die Tabellen nicht verwenden
    if (!isDatabaseConnected()) {
        return false;
    }
    
    // Standardverhalten: Für einige Tabellen wird die Datenbank verwendet, für andere JSON-Dateien
    $migrationConfig = [
        'users.json' => false,      // Benutzer aus Sicherheitsgründen weiterhin in JSON speichern
        'roles.json' => false,      // Rollen ebenfalls in JSON belassen für Berechtigungsmanagement
        'cases.json' => true,       // Fälle in der Datenbank speichern für bessere Leistung
        'documents.json' => true,   // Dokumente in der Datenbank speichern für bessere Leistung
        'equipment.json' => true,   // Ausrüstung in der Datenbank speichern
        'fines.json' => true,       // Bußgelder in der Datenbank speichern
        'court_calendar.json' => true, // Gerichtstermine in der Datenbank speichern
    ];
    
    // Im Entwicklungsmodus oder wenn wir keinen Datenbankeintrag haben, JSON verwenden
    $devMode = getenv('APP_ENV') === 'development';
    if ($devMode) {
        return false;
    }
    
    // Tabellennamen aus Dateinamen ableiten
    $tableName = getTableNameFromFilename($filename);
    
    // Prüfen, ob die Migration für diese Tabelle konfiguriert ist
    if (array_key_exists($filename, $migrationConfig)) {
        return $migrationConfig[$filename] && checkTableExists($tableName);
    }
    
    // Für alle anderen Tabellen prüfen, ob sie in der Datenbank existieren
    return checkTableExists($tableName);
}

/**
 * Hilfsfunktion, um zu prüfen, ob eine Tabelle in der Datenbank existiert
 *
 * @param string $tableName Der Tabellenname
 * @return bool True, wenn die Tabelle existiert
 */
function checkTableExists($tableName) {
    $pdo = getPDO();
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT to_regclass('public.$tableName')");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return $result !== null && $result !== false;
    } catch (Exception $e) {
        error_log("Error checking if table exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database table name from JSON filename
 *
 * @param string $filename The JSON filename
 * @return string The database table name
 */
function getTableNameFromFilename($filename) {
    return pathinfo($filename, PATHINFO_FILENAME);
}

/**
 * Insert a new record into a JSON file or database
 * 
 * @param string $filename The JSON file to add to or table name
 * @param array $record The record to add
 * @return mixed The record ID on success, false on failure
 */
function insertRecord($filename, $record) {
    // Ensure record has an ID
    if (!isset($record['id'])) {
        $record['id'] = generateUniqueId();
    }
    
    // Store the ID so we can return it
    $recordId = $record['id'];
    
    // Add creation timestamp if not present
    if (!isset($record['date_created'])) {
        $record['date_created'] = date('Y-m-d H:i:s');
    }
    
    // Try database first if connected
    $pdo = getPDO();
    if ($pdo !== null && isTableMigratedToDatabase($filename)) {
        $tableName = getTableNameFromFilename($filename);
        
        try {
            // Build SQL query
            $columns = array_keys($record);
            $placeholders = array_map(function($col) { return ":$col"; }, $columns);
            
            $sql = "INSERT INTO $tableName (" . implode(", ", $columns) . ") 
                   VALUES (" . implode(", ", $placeholders) . ")";
            
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            foreach ($record as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            if ($stmt->execute()) {
                return $recordId; // Return the record ID on successful insert
            } else {
                return false;
            }
        } catch (Exception $e) {
            error_log("Database insert error: " . $e->getMessage());
            // Fall back to JSON
        }
    }
    
    // Fall back to JSON file
    $data = loadJsonData($filename);
    $data[] = $record;
    
    if (saveJsonData($filename, $data)) {
        return $recordId; // Return the record ID if save was successful
    }
    
    return false; // Return false if save failed
}

/**
 * Update a record in a JSON file or database
 * 
 * @param string $filename The JSON file to update or table name
 * @param string $id The ID of the record to update
 * @param array $record The updated record data
 * @return bool True on success, false on failure
 */
function updateRecord($filename, $id, $record) {
    // Preserve the ID and add update timestamp
    $record['id'] = $id;
    $record['date_updated'] = date('Y-m-d H:i:s');
    
    // Try database first if connected
    $pdo = getPDO();
    if ($pdo !== null && isTableMigratedToDatabase($filename)) {
        $tableName = getTableNameFromFilename($filename);
        
        try {
            // Get the existing record to preserve creation date
            $stmt = $pdo->prepare("SELECT date_created FROM $tableName WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord && isset($existingRecord['date_created'])) {
                $record['date_created'] = $existingRecord['date_created'];
            }
            
            // Build SET clause for UPDATE query
            $setClause = [];
            foreach ($record as $key => $value) {
                $setClause[] = "$key = :$key";
            }
            
            $sql = "UPDATE $tableName SET " . implode(", ", $setClause) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            foreach ($record as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Database update error: " . $e->getMessage());
            // Fall back to JSON
        }
    }
    
    // Fall back to JSON file
    $data = loadJsonData($filename);
    $updated = false;
    
    foreach ($data as $key => $item) {
        if (isset($item['id']) && $item['id'] === $id) {
            // Preserve creation date if available
            if (isset($item['date_created'])) {
                $record['date_created'] = $item['date_created'];
            }
            
            $data[$key] = $record;
            $updated = true;
            break;
        }
    }
    
    if ($updated) {
        return saveJsonData($filename, $data);
    }
    
    return false;
}

/**
 * Delete a record from a JSON file or database
 * 
 * @param string $filename The JSON file to delete from or table name
 * @param string $id The ID of the record to delete
 * @return bool True on success, false on failure
 */
function deleteRecord($filename, $id) {
    // Try database first if connected
    $pdo = getPDO();
    if ($pdo !== null && isTableMigratedToDatabase($filename)) {
        $tableName = getTableNameFromFilename($filename);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM $tableName WHERE id = :id");
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Database delete error: " . $e->getMessage());
            // Fall back to JSON
        }
    }
    
    // Fall back to JSON file
    $data = loadJsonData($filename);
    $initialCount = count($data);
    
    $data = array_filter($data, function($item) use ($id) {
        return !isset($item['id']) || $item['id'] !== $id;
    });
    
    // Re-index array
    $data = array_values($data);
    
    // Only save if something was actually removed
    if (count($data) < $initialCount) {
        return saveJsonData($filename, $data);
    }
    
    return false;
}

/**
 * Query records based on criteria
 * 
 * @param string $filename The JSON file to query or table name
 * @param array $criteria The criteria to match (key-value pairs)
 * @return array Matching records
 */
function queryRecords($filename, $criteria = []) {
    // Try database first if connected
    $pdo = getPDO();
    if ($pdo !== null && isTableMigratedToDatabase($filename)) {
        $tableName = getTableNameFromFilename($filename);
        
        try {
            // Build query
            $whereClause = [];
            $params = [];
            
            if (!empty($criteria)) {
                foreach ($criteria as $key => $value) {
                    $whereClause[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }
            
            $sql = "SELECT * FROM $tableName";
            if (!empty($whereClause)) {
                $sql .= " WHERE " . implode(" AND ", $whereClause);
            }
            
            $stmt = $pdo->prepare($sql);
            
            // Bind parameters
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Database query error: " . $e->getMessage());
            // Fall back to JSON
        }
    }
    
    // Fall back to JSON file
    $data = getJsonData($filename);
    
    if (empty($criteria)) {
        return $data;
    }
    
    return array_filter($data, function($item) use ($criteria) {
        foreach ($criteria as $key => $value) {
            if (!isset($item[$key]) || $item[$key] != $value) {
                return false;
            }
        }
        return true;
    });
}

// Nur definieren, wenn die Funktion noch nicht existiert
if (!function_exists('loadJsonData')) {
    /**
     * Load JSON data from a file
     * 
     * @param string $filename The name of the JSON file (without path)
     * @return array The decoded JSON data
     */
    function loadJsonData($filename) {
        $filepath = __DIR__ . '/../data/' . $filename;
        
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }
        
        return [];
    }
}

// Nur definieren, wenn die Funktion noch nicht existiert
if (!function_exists('getJsonData')) {
    /**
     * Get data from JSON file
     * 
     * @param string $filename The name of the JSON file (without path)
     * @return array The data from the JSON file or empty array if file doesn't exist
     */
    function getJsonData($filename) {
        $filepath = __DIR__ . '/../data/' . $filename;
        
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        }
        
        return [];
    }
}

/**
 * Get the absolute file path for a data file
 * 
 * @param string $filename The name of the file (without path)
 * @return string The absolute path to the data file
 */
function getDataFilePath($filename) {
    return __DIR__ . '/../data/' . $filename;
}

// Nur definieren, wenn die Funktion noch nicht existiert
if (!function_exists('saveJsonData')) {
    /**
     * Save data to a JSON file
     * 
     * @param string $filename The name of the JSON file (without path)
     * @param array $data The data to save
     * @return bool True on success, false on failure
     */
    function saveJsonData($filename, $data) {
        $filepath = getDataFilePath($filename);
        $dir = dirname($filepath);
        
        // Ensure directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Format JSON with pretty print for readability
        $json = json_encode($data, JSON_PRETTY_PRINT);
        
        if ($json === false) {
            error_log("JSON encoding error: " . json_last_error_msg());
            return false;
        }
        
        return file_put_contents($filepath, $json) !== false;
    }
}

/**
 * Initialize data files with default data if they don't exist
 */
function initializeDataFiles() {
    $dataFiles = [
        'users.json' => [
            [
                'id' => 'admin',
                'username' => 'OConnor',
                'password' => password_hash('admin', PASSWORD_DEFAULT),
                'role' => 'Administrator',
                'is_admin' => true,
                'status' => 'active',
                'date_created' => date('Y-m-d H:i:s')
            ]
        ],
        'roles.json' => [
            [
                'id' => 'admin',
                'name' => 'Administrator',
                'description' => 'System administrator with full access'
            ],
            [
                'id' => 'prosecutor',
                'name' => 'Prosecutor',
                'description' => 'Department prosecutor responsible for cases'
            ],
            [
                'id' => 'judge',
                'name' => 'Judge',
                'description' => 'Judicial official who presides over cases'
            ],
            [
                'id' => 'clerk',
                'name' => 'Clerk',
                'description' => 'Administrative staff who manages records'
            ]
        ],
        'themes.json' => [
            [
                'name' => 'Federal Archive (1899)',
                'active' => true,
                'colors' => [
                    'primary' => '#0e1214',
                    'secondary' => '#5a4a37',
                    'accent' => '#6a2e09',
                    'light' => '#e8e2d8',
                    'dark' => '#0e0e0e',
                    'paper' => '#a19a88',
                    'ink' => '#121212',
                    'success' => '#1c3821',
                    'danger' => '#660000',
                    'warning' => '#6a5800',
                    'info' => '#192a41',
                    'sidebar-bg' => '#1a1f23',
                    'sidebar-color' => '#e8e2d8',
                    'content-bg' => '#d0c9bc',
                ]
            ],
            [
                'name' => 'Sepia Archive',
                'active' => false,
                'colors' => [
                    'primary' => '#2c1704',
                    'secondary' => '#6b4f34',
                    'accent' => '#8b4513',
                    'light' => '#f2e8d5',
                    'dark' => '#1a0d02',
                    'paper' => '#d9c7a7',
                    'ink' => '#0d0901',
                    'success' => '#2d4925',
                    'danger' => '#7b0000',
                    'warning' => '#7a6307',
                    'info' => '#1a384f',
                    'sidebar-bg' => '#2c1704',
                    'sidebar-color' => '#f2e8d5',
                    'content-bg' => '#e8dbbd',
                ]
            ]
        ],
        'cases.json' => [],
        'defendants.json' => [],
        'calendar.json' => [],
        'notes.json' => [],
        'folders.json' => [],
        'indictments.json' => [],
        'templates.json' => [],
        'staff.json' => [],
        'equipment.json' => [],
        'duty_log.json' => [],
        'equipment_types.json' => [
            [
                'id' => 'badge',
                'name' => 'Badge',
                'description' => 'Official department badge'
            ],
            [
                'id' => 'firearm',
                'name' => 'Firearm',
                'description' => 'Service weapon'
            ],
            [
                'id' => 'uniform',
                'name' => 'Uniform',
                'description' => 'Official department uniform'
            ],
            [
                'id' => 'handcuffs',
                'name' => 'Handcuffs',
                'description' => 'Restraint device'
            ]
        ]
    ];
    
    foreach ($dataFiles as $filename => $defaultData) {
        $filepath = __DIR__ . '/../data/' . $filename;
        
        // Create directory if it doesn't exist
        $directory = dirname($filepath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Create file with default data if it doesn't exist
        if (!file_exists($filepath)) {
            file_put_contents($filepath, json_encode($defaultData, JSON_PRETTY_PRINT));
        }
    }
}

// Initialize data files when this file is included
initializeDataFiles();
