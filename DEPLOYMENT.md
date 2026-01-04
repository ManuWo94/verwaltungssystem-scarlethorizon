# Deployment-Anleitung für das Justice Department Records Management System

Diese Anleitung beschreibt, wie das System bei einem Deployment korrekt aktualisiert werden kann, ohne dass vorhandene Daten verloren gehen.

## Deployment-Optionen

Es gibt zwei verschiedene Deployment-Szenarien:

1. **Standard-Deployment**: Aktualisiert nur den Code, behält aber alle vorhandenen Daten bei.
2. **Vollständiges Deployment**: Aktualisiert den Code und setzt die Datenbank zurück.

## 1. Standard-Deployment (Daten behalten)

Beim Standard-Deployment werden nur die Programmcode-Dateien aktualisiert, während die Daten (in JSON-Dateien oder der Datenbank) unverändert bleiben. Diese Option ist zu bevorzugen, wenn bereits Echtdaten im System vorhanden sind.

### Vorgehensweise mit den Hilfsskripten:

1. Führen Sie das Backup-Skript aus, um alle Daten zu sichern:
   ```bash
   php backup_data.php
   ```

2. Aktualisieren Sie den Code (z.B. durch Git oder manuelle Aktualisierung)

3. Falls Daten verloren gegangen sind, stellen Sie sie mit dem Wiederherstellungsskript wieder her:
   ```bash
   php restore_data.php
   ```

4. Starten Sie den Server neu

### Alternative manuelle Vorgehensweise:

1. Erstellen Sie eine Kopie des `data`-Verzeichnisses als Backup
2. Aktualisieren Sie alle Code-Dateien außer dem `data`-Verzeichnis
3. Starten Sie den Server neu

```bash
# Beispiel (Unix/Linux)
cp -r data data_backup
git pull  # oder Ihre bevorzugte Methode, um den Code zu aktualisieren
# Starten Sie den Server neu
```

## 2. Vollständiges Deployment (Daten zurücksetzen)

Beim vollständigen Deployment werden sowohl der Code als auch die Datenbank auf den Standardzustand zurückgesetzt. Diese Option sollte nur in folgenden Fällen verwendet werden:

- Bei der ersten Installation
- In einer Testumgebung
- Wenn explizit ein Neustart mit frischen Daten gewünscht ist

### Vorgehensweise:

1. Aktualisieren Sie alle Code-Dateien
2. Führen Sie das Reset-Skript aus

```bash
# Beispiel (Unix/Linux)
git pull  # oder Ihre bevorzugte Methode, um den Code zu aktualisieren
php reset_database.php
# Starten Sie den Server neu
```

## Wichtige Hinweise

- **Sichern Sie immer Ihre Daten**, bevor Sie ein Deployment durchführen
- **Testen Sie das Deployment** in einer Testumgebung, bevor Sie es in der Produktionsumgebung anwenden
- Bei Problemen können Sie das Backup wiederherstellen

## Replit-spezifische Hinweise

Wenn Sie das System in Replit deployen, beachten Sie bitte die zusätzlichen Informationen in der `REPLIT_DEPLOYMENT.md`. Das System ist so konfiguriert, dass es in Replit automatisch JSON-Dateien für die Datenspeicherung verwendet, wenn keine PostgreSQL-Datenbank verfügbar ist.