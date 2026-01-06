# Rollenbasierte Aufgabenverwaltung - Dokumentation

## Übersicht
Das Aufgabenverwaltungssystem wurde erweitert, um rollenbasierte Aufgabenzuweisungen zu unterstützen. Dies ermöglicht eine flexiblere und teamorientierte Aufgabenverteilung.

## Neue Funktionen

### 1. Aufgabenzuweisung an Rollen
- **Beschreibung**: Aufgaben können jetzt nicht nur spezifischen Benutzern, sondern auch ganzen Rollen zugewiesen werden
- **Verwendung**: Beim Erstellen einer Aufgabe kann zwischen "Benutzer zuweisen" und "Rolle zuweisen" gewählt werden
- **Vorteil**: Alle Benutzer mit der zugewiesenen Rolle sehen die Aufgabe und können sie beanspruchen

### 2. Aufgabenbeanspruchung (Claim/Unclaim)
- **Claim**: Benutzer mit der zugewiesenen Rolle können Rollenaufgaben beanspruchen
- **Unclaim**: Beanspruchte Aufgaben können wieder freigegeben werden (durch den Beanspruchenden oder Admins)
- **Sichtbarkeit**: Es ist für alle ersichtlich, wer eine Aufgabe beansprucht hat
- **Status**: Nicht beanspruchte Rollenaufgaben werden als "Noch nicht beansprucht" markiert

### 3. Erweiterte Berechtigungen

#### Aufgaben abhaken/erledigen
- **Rollenaufgaben**: Nur wer die Aufgabe beansprucht hat (oder Admins/Ersteller) kann sie abhaken
- **Benutzeraufgaben**: Zugewiesener Benutzer, Ersteller oder Admins können abhaken
- **Transparenz**: Es wird gespeichert und angezeigt, wer die Aufgabe erledigt hat

#### Aufgaben löschen
- **Berechtigt**: Nur der Ersteller der Aufgabe oder Administratoren
- **Einschränkung**: Andere Benutzer (auch wenn zugewiesen) können Aufgaben nicht löschen

#### Aufgaben kommentieren
- **Berechtigt**: ALLE Benutzer können alle Aufgaben einsehen und kommentieren
- **Transparenz**: Fördert die Zusammenarbeit und ermöglicht Feedback

### 4. Benachrichtigungssystem
- **Rollenaufgaben**: Alle Benutzer mit der zugewiesenen Rolle erhalten eine Benachrichtigung
- **Benutzeraufgaben**: Der zugewiesene Benutzer erhält eine Benachrichtigung
- **Kommentare**: Zugewiesene und Ersteller werden über neue Kommentare informiert

## Datenstruktur

### Neue Felder in Aufgaben-Objekten
```json
{
  "id": "eindeutige_id",
  "title": "Aufgabentitel",
  "description": "Beschreibung",
  "assignment_type": "role|user",  // NEU: Art der Zuweisung
  "assigned_to": "rolle_id oder user_id",
  "claimed_by": "user_id",  // NEU: Wer hat die Aufgabe beansprucht (nur bei Rollenaufgaben)
  "claimed_at": "2026-01-06 12:00:00",  // NEU: Wann wurde beansprucht
  "completed_by": "user_id",  // Wer hat die Aufgabe erledigt
  "completed_at": "2026-01-06 14:00:00",
  "created_by": "user_id",
  "created_at": "2026-01-06 10:00:00",
  "comments": []
}
```

## API-Endpunkte

### Neue AJAX-Aktionen
1. **claim_task**: Aufgabe beanspruchen
   - Parameter: `task_id`
   - Rückgabe: Erfolgsmeldung und aktualisierte Aufgabe

2. **unclaim_task**: Beanspruchung aufheben
   - Parameter: `task_id`
   - Rückgabe: Erfolgsmeldung und aktualisierte Aufgabe

### Erweiterte Aktionen
- **get_task**: Jetzt mit Claim-Informationen
- **create_task**: Unterstützt `assignment_type` Parameter
- **toggle_task_status**: Berücksichtigt Claim-Status bei Rollenaufgaben

## Benutzeroberfläche

### Aufgabenübersicht
- **Rollenspalte**: Zeigt "(Rolle)" bei Rollenzuweisungen
- **Beanspruchungsstatus**: Zeigt an, ob und von wem eine Rollenaufgabe beansprucht wurde
- **Action-Buttons**:
  - 👁️ Ansehen (alle Benutzer)
  - ✋ Beanspruchen (nur bei verfügbaren Rollenaufgaben)
  - ↩️ Freigeben (nur bei eigenen beanspruchten Aufgaben)
  - ➡️ Weiterleiten (nur Zugewiesene/Ersteller)
  - ✏️ Bearbeiten (nur Zugewiesene/Ersteller)
  - 🗑️ Löschen (nur Ersteller/Admins)

### Aufgabe erstellen Modal
- **Radio-Buttons**: Wahl zwischen Benutzer- und Rollenzuweisung
- **Dynamische Dropdowns**: Zeigt entweder Benutzer oder Rollen
- **Hilfetext**: Erklärt das Rollenkonzept

### Aufgabendetails Modal
- **Erweiterte Anzeige**: Zeigt Beanspruchungsinformationen bei Rollenaufgaben
- **Kommentarbereich**: Für alle Benutzer zugänglich

## Workflow-Beispiele

### Szenario 1: Rollenaufgabe erstellen
1. Admin erstellt Aufgabe "Akteneinsicht prüfen"
2. Weist sie der Rolle "Gerichtsreferendar" zu
3. Alle Gerichtsreferendare erhalten Benachrichtigung
4. Ein Referendar beansprucht die Aufgabe
5. Andere sehen, dass die Aufgabe bereits beansprucht ist
6. Der Beanspruchende bearbeitet und schließt die Aufgabe ab

### Szenario 2: Kommentare und Zusammenarbeit
1. Benutzer A hat eine Aufgabe
2. Benutzer B sieht die Aufgabe in der Liste
3. Benutzer B öffnet Details und kommentiert
4. Benutzer A erhält Benachrichtigung über Kommentar
5. Beide können weiter diskutieren

### Szenario 3: Aufgabe freigeben
1. Benutzer beansprucht Rollenaufgabe
2. Stellt fest, dass er sie nicht bearbeiten kann
3. Gibt Aufgabe über "Freigeben"-Button wieder frei
4. Andere Benutzer mit der Rolle können sie nun beanspruchen

## Sicherheit und Berechtigungen

### Prüfungen
- Claim nur durch Benutzer mit der richtigen Rolle
- Unclaim nur durch Beanspruchenden oder Admins
- Toggle-Status prüft Claim bei Rollenaufgaben
- Löschen nur durch Ersteller oder Admins
- Alle Aktionen werden validiert

### Audit-Trail
- Alle Änderungen werden mit `updated_by` und `updated_at` protokolliert
- Erledigung wird mit `completed_by` gespeichert
- Beanspruchung wird mit `claimed_by` und `claimed_at` gespeichert

## Migration bestehender Aufgaben
- Bestehende Aufgaben ohne `assignment_type` werden automatisch als `user`-Zuweisungen behandelt
- Fehlende Felder (`claimed_by`, `claimed_at`) werden bei Bedarf hinzugefügt
- Keine Datenbankmigration erforderlich (JSON-basiert)

## Technische Details

### Dateien
- **Hauptdatei**: `/modules/task_assignments.php` (erweitert)
- **Datenspeicher**: `/data/tasks/assigned_tasks.json`
- **Rollendaten**: `/data/roles.json`

### JavaScript-Funktionen
- `claim-task-btn`: Event-Handler für Beanspruchung
- `unclaim-task-btn`: Event-Handler für Freigabe
- Dynamisches Umschalten zwischen Benutzer-/Rollenzuweisung im Formular

### PHP-Funktionen
- Erweiterte Berechtigungsprüfungen in allen AJAX-Handlern
- Rollenbasierte Benachrichtigungen
- Sichtbarkeitsfilterung nach Rollen

## Best Practices
1. **Rollenaufgaben** für wiederkehrende, nicht personengebundene Tätigkeiten verwenden
2. **Benutzeraufgaben** für spezifische, personalisierte Aufgaben nutzen
3. Aufgaben zeitnah beanspruchen, um Doppelarbeit zu vermeiden
4. Kommentarfunktion für Rückfragen und Updates nutzen
5. Nicht bearbeitbare Aufgaben zeitnah freigeben

## Support und Fehlerbehebung
- Bei Problemen mit Berechtigungen: Überprüfen Sie die Rollenzuweisungen in den Benutzereinstellungen
- Benachrichtigungen funktionieren nicht: Prüfen Sie die Benachrichtigungseinstellungen
- Aufgabe kann nicht beansprucht werden: Stellen Sie sicher, dass Sie die erforderliche Rolle haben

## Zukünftige Erweiterungen (optional)
- Mehrfachzuweisung an mehrere Rollen
- Deadline-Benachrichtigungen
- Statistiken über Aufgabenbearbeitung pro Rolle
- Filter nach beanspruchten/unbeanspruchten Rollenaufgaben
