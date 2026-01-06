<?php
/**
 * Cache-Clearer für Plesk-Server
 * Rufe diese Datei im Browser auf, um PHP OPcache zu leeren
 */

echo "<h1>Cache Clearer</h1>";

// OPcache leeren
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<p style='color: green;'>✅ OPcache erfolgreich geleert!</p>";
    } else {
        echo "<p style='color: red;'>❌ OPcache konnte nicht geleert werden</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ OPcache ist nicht aktiviert</p>";
}

// Realpath Cache leeren
clearstatcache(true);
echo "<p style='color: green;'>✅ Realpath Cache geleert</p>";

// Aktuelle Datei-Info anzeigen
echo "<hr>";
echo "<h2>Datei-Status</h2>";

$licenseFile = __DIR__ . '/modules/licenses.php';
if (file_exists($licenseFile)) {
    echo "<p><strong>modules/licenses.php gefunden</strong></p>";
    echo "<p>Größe: " . filesize($licenseFile) . " Bytes</p>";
    echo "<p>Letzte Änderung: " . date('Y-m-d H:i:s', filemtime($licenseFile)) . "</p>";
    
    // Prüfe ob db.php Include vorhanden ist
    $content = file_get_contents($licenseFile);
    if (strpos($content, "require_once __DIR__ . '/../includes/db.php';") !== false) {
        echo "<p style='color: green; font-weight: bold;'>✅ db.php Include VORHANDEN</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ db.php Include FEHLT - Server hat alte Version!</p>";
    }
    
    // Zeige erste 10 Zeilen
    $lines = explode("\n", $content);
    echo "<h3>Erste 10 Zeilen der Datei:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
    for ($i = 0; $i < 10 && $i < count($lines); $i++) {
        echo htmlspecialchars($lines[$i]) . "\n";
    }
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ modules/licenses.php nicht gefunden!</p>";
}

echo "<hr>";
echo "<h2>Nächste Schritte</h2>";
echo "<ol>";
echo "<li>Lade diese Seite neu (F5)</li>";
echo "<li>Wenn 'db.php Include FEHLT' angezeigt wird: Git Pull auf dem Server ausführen</li>";
echo "<li>Wenn Include vorhanden ist: Gehe zu <a href='modules/licenses.php'>modules/licenses.php</a> und teste</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='?' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>🔄 Cache erneut leeren</a></p>";
?>
