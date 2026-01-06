<?php
/**
 * Einfacher Deploy-Trigger (ohne Shell-Zugriff)
 * Erzwingt Neuladung durch Datei-Touch
 */

echo "<h1>Deployment Trigger</h1>";

// Versuche, Dateien zu aktualisieren
$files = [
    __DIR__ . '/modules/licenses.php',
    __DIR__ . '/modules/license_categories.php'
];

echo "<h2>Datei-Status:</h2>";
foreach ($files as $file) {
    if (file_exists($file)) {
        // Datei-Timestamp aktualisieren
        touch($file);
        clearstatcache(true, $file);
        
        echo "<p style='color: green;'>✅ " . basename($file) . " aktualisiert</p>";
        echo "<small>Größe: " . filesize($file) . " Bytes | ";
        echo "Geändert: " . date('Y-m-d H:i:s', filemtime($file)) . "</small><br>";
        
        // Prüfe db.php Include
        $content = file_get_contents($file);
        if (strpos($content, "includes/db.php") !== false) {
            echo "<span style='color: green; font-weight: bold;'>✅ db.php Include vorhanden</span>";
        } else {
            echo "<span style='color: red; font-weight: bold;'>❌ db.php Include FEHLT</span>";
        }
        echo "<br><br>";
    } else {
        echo "<p style='color: red;'>❌ " . basename($file) . " nicht gefunden</p>";
    }
}

// OPcache leeren wenn möglich
echo "<hr><h2>Cache-Status:</h2>";
if (function_exists('opcache_reset')) {
    if (opcache_reset()) {
        echo "<p style='color: green;'>✅ OPcache geleert</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ OPcache konnte nicht geleert werden</p>";
    }
} else {
    echo "<p style='color: gray;'>ℹ️ OPcache nicht aktiviert</p>";
}

echo "<hr>";
echo "<h2>Nächste Schritte:</h2>";
echo "<ol>";
echo "<li>Drücke <strong>Strg+Shift+R</strong> (Hard Refresh) in deinem Browser</li>";
echo "<li>Gehe zu <a href='modules/licenses.php' style='color: blue; font-weight: bold;'>Lizenzverwaltung</a></li>";
echo "<li>Teste das Erstellen einer neuen Lizenz</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='?' style='padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>🔄 Erneut ausführen</a></p>";
?>
