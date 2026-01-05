# Drag & Drop Permission Editor - Benutzerhandbuch

## Überblick
Das Drag & Drop Permission Editor ist eine moderne, benutzerfreundliche UI zur Verwaltung von Rollen und Berechtigungen im Aktenverwaltungssystem. Sie können Berechtigungen ganz einfach per Drag & Drop zwischen verfügbaren Aktionen und erteilten Berechtigungen verschieben.

## Zugriff auf die Permission Editor

1. **Admin-Dashboard öffnen**: Melden Sie sich als Administrator an
2. **Navigation**: Gehen Sie zu `Admin > Rollen`
3. **Berechtigungen bearbeiten**: Klicken Sie auf das Berechtigungen-Icon (Zahnrad) neben einer Rolle

## Benutzeroberfläche

### Zwei-Spalten-Layout

#### Linke Spalte: **Module & Aktionen**
- Enthält alle verfügbaren Berechtigungen, die nicht erteilt sind
- Grauer Hintergrund
- Draggable Items mit Modul-Name und Aktionsbeschreibung

#### Rechte Spalte: **Erteilte Berechtigungen**
- Enthält alle aktuell der Rolle erteilten Berechtigungen
- Grüner Hintergrund (#f0f9f6)
- Items mit grünem Highlight (#d4edda)

## Operationen

### Permission erteilen
1. Öffnen Sie das Permission Modal
2. **Suchen** Sie in der linken Spalte die gewünschte Permission
3. **Ziehen** Sie das Item auf die rechte Spalte
4. Item wird grün hinterlegt
5. **Speichern** Sie die Änderungen

### Permission entfernen
1. Öffnen Sie das Permission Modal
2. **Finden** Sie in der rechten Spalte die zu entfernende Permission
3. **Ziehen** Sie das Item zurück in die linke Spalte
4. Item verliert die grüne Hinterlegung
5. **Speichern** Sie die Änderungen

## Visuelle Feedback-Zustände

| Zustand | Beschreibung | Aussehen |
|---------|-------------|----------|
| **Normal** | Item ist draggable und bereit | Weiß mit grauer Grenze |
| **Hover** | Maus über Item | Leicht grauer Hintergrund |
| **Dragging** | Item wird gerade gezogen | Halb-transparent (50% Opazität) |
| **Drag Over** | Item über Drop-Zone | Zone wird farblich hervorgehoben |
| **Granted** | Item in erteilten Berechtigungen | Grüner Hintergrund |

## Technische Details

### Speicherung
- Berechtigungen werden in `data/roles.json` als JSON-Array gespeichert
- Format: `{ "roleId": { "module": ["action1", "action2"] } }`
- Änderungen sind sofort wirksam für Benutzer mit dieser Rolle

### Initialisierung
Das Drag & Drop-System wird beim Öffnen eines Berechtigungsmodals automatisch initialisiert via Bootstrap's `shown.bs.modal` Event.

### Performance
- Keine Abhängigkeit von externen Bibliotheken
- Verwendet native HTML5 Drag & Drop API
- Asynchrone Event-Handler für schnelle Reaktion

## Häufige Probleme

### Items verschieben sich nicht
- **Lösung**: Stellen Sie sicher, dass JavaScript aktiviert ist
- **Lösung**: Aktualisieren Sie die Seite (F5)

### Änderungen werden nicht gespeichert
- **Lösung**: Klicken Sie auf "Speichern" Button am Ende des Modals
- **Lösung**: Überprüfen Sie Browserkonsole auf Fehler (F12)

### Modal öffnet sich nicht
- **Lösung**: Stellen Sie sicher, dass jQuery und Bootstrap JavaScript laden
- **Lösung**: Prüfen Sie, ob `data-toggle="modal"` korrekt gesetzt ist

## Beispiele

### Beispiel 1: Judge-Rolle mit neuen Berechtigungen
```
Ausgangszustand:
- cases: view, create, edit
- indictments: view

Gewünscht:
- cases: view, create, edit, delete
- indictments: view, schedule

Aktion:
1. Ziehe "cases:delete" nach rechts
2. Ziehe "indictments:schedule" nach rechts
3. Speichern
```

### Beispiel 2: Entfernen von Berechtigungen
```
Ausgangszustand:
- cases: view, create, edit, delete

Gewünscht:
- cases: view

Aktion:
1. Ziehe "cases:create" nach links
2. Ziehe "cases:edit" nach links
3. Ziehe "cases:delete" nach links
4. Speichern
```

## API-Integration (für Entwickler)

### JavaScript-Funktionen

```javascript
// Manuell initialisieren
initPermissionDragDrop();

// Drag Start Handler
function handleDragStart(e) { ... }

// Drop Handler
function handleDrop(e) { ... }
```

### Form-Daten beim Speichern

Das Modal sendet folgende POST-Daten:
```php
$_POST['role_id']  // Role ID
$_POST['permissions'] = [
    'module1' => ['action1', 'action2'],
    'module2' => ['action3']
]
```

## Admin-Panel Verwaltung

Die `admin/roles.php` Seite handhagt:
- Anzeige aller Rollen
- Modal für jede Rolle
- Speicherung der Permission-Änderungen
- Bestätigung der Speicherung mit Success/Error Messages

---

**Version**: 1.0  
**Letzte Aktualisierung**: 2024-12  
**Status**: Produktionsreif ✓
