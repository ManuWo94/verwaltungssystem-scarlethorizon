# Replit-spezifische Deployment-Anleitung

Diese Anleitung beschreibt speziell, wie das Justice Department Records Management System in der Replit-Umgebung deployed werden kann, ohne dass dabei Daten verloren gehen.

## Besonderheiten der Replit-Umgebung

Die Replit-Umgebung hat einige Besonderheiten, die bei der Verwendung des Systems berücksichtigt werden müssen:

1. **Keine PostgreSQL-Erweiterung**: In der Replit-Umgebung ist die PHP-PostgreSQL-Erweiterung standardmäßig nicht verfügbar. Das System wird daher automatisch auf die JSON-Datenspeicherung zurückgreifen.

2. **Umgebungsvariablen**: Die Anwendung versucht automatisch, die fehlenden Umgebungsvariablen mit Standardwerten zu setzen, damit keine Fehler auftreten.

3. **Development-Modus**: Die Anwendung läuft in der Replit-Umgebung standardmäßig im "development"-Modus, um die JSON-Datenspeicherung zu erzwingen.

## Deployment-Optionen in Replit

Es gibt zwei Möglichkeiten, das System in Replit zu deployen:

### 1. Standard-Deployment (Daten behalten)

Wenn Sie bereits Echtdaten im System haben, sollten Sie diese Option wählen:

1. **Vor dem Deployment**: Führen Sie das Backup-Skript aus, um alle Daten vollständig zu sichern:
   ```bash
   php backup_data.php
   ```

2. **Snapshot erstellen**: Erstellen Sie zusätzlich einen Fork oder einen Snapshot in Replit, um Ihre Daten zu sichern

3. **Deployment ausführen**: Führen Sie das Deployment über die Replit-Oberfläche aus

4. **Nach dem Deployment**: Falls Daten verloren gegangen sind, stellen Sie sie mit dem Wiederherstellungsskript wieder her:
   ```bash
   php restore_data.php
   ```

5. **Webserver neu starten**: Starten Sie den Server neu über den "Run"-Button

**Alternative manuelle Methode:**
1. **Daten sichern**: Kopieren Sie das `data`-Verzeichnis nach `data_backup`:
   ```bash
   cp -r data data_backup
   ```
2. **Deployment ausführen**: Führen Sie das Deployment über die Replit-Oberfläche aus
3. **Nach dem Deployment**: Überprüfen Sie, ob der `data`-Ordner noch intakt ist. Falls nicht, stellen Sie ihn wieder her:
   ```bash
   rm -rf data
   cp -r data_backup data
   ```

### 2. Vollständiges Deployment (Daten zurücksetzen)

Wenn Sie mit frischen Standarddaten beginnen möchten:

1. **Deployment ausführen**: Führen Sie das Deployment über die Replit-Oberfläche aus
2. **Datenbank zurücksetzen**: Führen Sie das Reset-Skript aus:
   ```bash
   php reset_database.php
   ```
3. **Webserver starten**: Starten Sie den Server über den "Run"-Button

## Datensicherung in Replit

Da die Datenpersistenz in Replit von großer Bedeutung ist, sollten Sie regelmäßige Sicherungen durchführen:

1. **Manuelle Sicherung**: Kopieren Sie das `data`-Verzeichnis regelmäßig:
   ```bash
   # Erstellt ein datiertes Backup im Ordner backups/
   mkdir -p backups/$(date +%Y-%m-%d)
   cp -r data/* backups/$(date +%Y-%m-%d)/
   ```

2. **Replit-Snapshots**: Erstellen Sie regelmäßig Snapshots Ihres Replit-Projekts

3. **Externe Sicherung**: Exportieren Sie wichtige Daten regelmäßig in eine externe Datei oder einen externen Dienst

## Bekannte Einschränkungen in Replit

1. **Leistung**: Die JSON-Datenspeicherung ist weniger leistungsfähig als eine relationale Datenbank. Bei großen Datenmengen kann es zu Performance-Einbußen kommen.

2. **Skalierbarkeit**: Das System ist in der Replit-Umgebung nicht für hohe Lasten oder viele gleichzeitige Benutzer ausgelegt.

3. **Datenpersistenz**: Achten Sie darauf, dass die JSON-Dateien nicht versehentlich gelöscht werden. Regelmäßige Backups sind empfehlenswert.

## Fehlersuche in Replit

1. **Server startet nicht**: Überprüfen Sie, ob der Port 5000 frei ist. Stellen Sie sicher, dass kein anderer Prozess diesen Port blockiert.

2. **Datenbank-Fehlermeldungen**: Diese können ignoriert werden, solange die Meldung "Verwende JSON-Dateien als Fallback" erscheint.

3. **Fehlende Daten**: Überprüfen Sie, ob die JSON-Dateien im `data/`-Verzeichnis existieren und lesbar sind.

4. **Daten nach Deployment verschwunden**: Stellen Sie das Backup des `data`-Verzeichnisses wieder her, wie im Abschnitt "Standard-Deployment" beschrieben.

## Support und weitere Hilfe

Bei Fragen oder Problemen mit dem Deployment in Replit wenden Sie sich bitte an das Entwicklungsteam.