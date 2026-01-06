# Lizenzverwaltungssystem - Dokumentation

## Übersicht

Das Lizenzverwaltungssystem ermöglicht die Erstellung, Verwaltung und Archivierung verschiedener Lizenztypen mit automatischen Benachrichtigungen und flexiblen Vorlagen.

## Hauptfunktionen

### 1. **Lizenzen erstellen** (`modules/licenses.php`)
- **Zweistufiger Prozess**:
  1. Kategorie auswählen
  2. Daten eingeben und Lizenz erstellen
- **Automatische Lizenznummern**: Nach konfiguriertem Schema (z.B. BL-2026-0001)
- **Vorschau**: Lizenznummer wird vor Erstellung angezeigt
- **Flexible Felder**: Dynamische Eingabefelder basierend auf Kategorie
- **Laufzeit**: Konfigurierbare Gültigkeitsdauer
- **Benachrichtigungen**: Optional mit einstellbarer Vorwarnzeit

### 2. **Kategorien verwalten** (`modules/license_categories.php`)
- **Nur für Admins**
- **Kategorien definieren**:
  - Name und Nummern-Schema
  - Standardlaufzeit in Tagen
  - Benachrichtigungseinstellungen
  - Textvorlage mit Platzhaltern
  - Benutzerdefinierte Felder
- **Status**: Kategorien können aktiviert/deaktiviert werden

### 3. **Archiv** (`modules/license_archive.php`)
- **Automatische Archivierung**: Abgelaufene Lizenzen werden automatisch archiviert
- **Funktionen**:
  - Einzelne Lizenzen löschen
  - Mehrfachauswahl mit Checkboxen
  - Sammellöschung nach Alter (30/60/90/180/365 Tage)
  - Lizenzen reaktivieren
- **Übersichtlich**: Anzeige wie lange Lizenzen abgelaufen sind

### 4. **Lizenz erneuern**
- Erstellt neue Lizenz mit neuer Nummer
- Übernimmt alle Daten der alten Lizenz
- Alte Lizenz bleibt im Archiv

## Platzhalter-System

### Nummern-Schema
- `{YEAR}` - Aktuelles Jahr (2026)
- `{NUM:X}` - Fortlaufende Nummer mit X Stellen
  - Beispiel: `{NUM:4}` → 0001, 0002, ...

### Textvorlagen
**Systemfelder:**
- `{LICENSE_NUMBER}` - Generierte Lizenznummer
- `{START_DATE}` - Gültig ab
- `{END_DATE}` - Gültig bis
- `{ISSUE_DATE}` - Ausstellungsdatum
- `{ISSUER_NAME}` - Ersteller
- `{ISSUER_ROLE}` - Rolle (ohne Klammern)

**Benutzerdefinierte Felder:**
- Jedes definierte Feld kann mit `{FELDNAME}` verwendet werden
- Beispiel: `{HOLDER_NAME}`, `{BUSINESS_TYPE}`, `{ADDRESS}`

## UI-Konzept

### Hauptseite (Lizenzen)
```
┌─────────────────────────────────────────┐
│ [Archiv] [Neue Lizenz]                  │
├─────────────────────────────────────────┤
│ Statistiken: [Aktiv] [Kategorien] [...] │
├─────────────────────────────────────────┤
│ Filter: [Kategorie ▼] [Suche...] [Reset]│
├─────────────────────────────────────────┤
│ Tabelle:                                │
│ Nr. | Kategorie | Inhaber | Bis | Status│
│ ... | ...       | ...     | ... | [👁️🔄🗑️]│
└─────────────────────────────────────────┘
```

### Erstellung (Modal)
```
Schritt 1: Kategorie wählen
┌──────────────────────────────┐
│ Kategorie: [Business ▼]      │
│         [Weiter →]           │
└──────────────────────────────┘

Schritt 2: Daten eingeben
┌──────────────────────────────┐
│ Lizenznummer: BL-2026-0042   │
│ ─────────────────────────    │
│ Inhaber: [____________]      │
│ Typ:     [____________]      │
│ ...                          │
│ Laufzeit: [365] Tage         │
│ ☑ Benachrichtigung [7] Tage  │
│ ←[Zurück]  [Erstellen ✓]     │
└──────────────────────────────┘
```

### Archiv
```
┌─────────────────────────────────────────┐
│ [Zurück] [Sammellöschung]               │
├─────────────────────────────────────────┤
│ Statistiken: [Archiviert] [>90 Tage]    │
├─────────────────────────────────────────┤
│ [Suche...] [Reset] [Alle ☑] [Auswahl 🗑️]│
├─────────────────────────────────────────┤
│ ☐ | Nr. | ... | Tage | [👁️↻🗑️]        │
│ ☐ | ... | ... | ...  | [👁️↻🗑️]        │
└─────────────────────────────────────────┘
```

## Automatisierung

### Cron-Job (`license_cron.php`)
**Täglich ausführen:**
```bash
php /pfad/zu/license_cron.php
```

**Funktionen:**
- Archiviert abgelaufene Lizenzen automatisch
- Sendet Benachrichtigungen X Tage vor Ablauf
- Markiert gesendete Benachrichtigungen

## Berechtigungen

| Aktion | Berechtigung |
|--------|--------------|
| Lizenzen anzeigen | `licenses.view` |
| Lizenz erstellen | `licenses.create` |
| Lizenz löschen | `licenses.delete` |
| Kategorien verwalten | Admin |

## Bedienung - Best Practices

### Häufige Aktionen
1. **Neue Lizenz**: Kategorie wählen → Felder ausfüllen → Erstellen
2. **Lizenz ansehen**: 👁️-Button → Text anzeigen/kopieren
3. **Lizenz erneuern**: 🔄-Button → Daten prüfen → Erstellen
4. **Archiv aufräumen**: Sammellöschung → Alter wählen → Bestätigen

### Workflow
```
Erstellen → Aktiv → (Benachrichtigung) → Ablauf → Archiviert → Löschen
                ↓
              Erneuern (neue Lizenz)
```

## Beispiel-Kategorien

### Business License
- **Schema**: `BL-{YEAR}-{NUM:4}`
- **Laufzeit**: 365 Tage
- **Felder**: Inhaber, Geschäftstyp, Adresse

### Weapon License
- **Schema**: `WL-{YEAR}-{NUM:4}`
- **Laufzeit**: 730 Tage (2 Jahre)
- **Felder**: Inhaber, Geburtsdatum, Waffentyp, Seriennummer

### Driver's License
- **Schema**: `DL-{YEAR}-{NUM:5}`
- **Laufzeit**: 1825 Tage (5 Jahre)
- **Felder**: Inhaber, Geburtsdatum, Fahrzeugklasse, Einschränkungen

## Datenstruktur

### licenses.json
```json
{
  "id": "lic_...",
  "category_id": "cat_business",
  "license_number": "BL-2026-0001",
  "start_date": "2026-01-06",
  "end_date": "2027-01-06",
  "status": "active|archived",
  "notification_enabled": true,
  "notification_days_before": 7,
  "notification_sent": false,
  "fields": {
    "HOLDER_NAME": "John Doe",
    "BUSINESS_TYPE": "General Store"
  },
  "license_text": "...",
  "created_by": "user_id",
  "renewed_from": "lic_old_id"
}
```

### license_categories.json
```json
{
  "id": "cat_business",
  "name": "Business License",
  "active": true,
  "number_schema": "BL-{YEAR}-{NUM:4}",
  "default_duration_days": 365,
  "notification_enabled": true,
  "notification_days_before": 7,
  "template": "...",
  "fields": [
    {
      "name": "HOLDER_NAME",
      "label": "Inhabername",
      "type": "text",
      "required": true
    }
  ]
}
```

## Tipps

### Nummern-Schema
- **Kurz und prägnant**: `WL-{YEAR}-{NUM:4}` statt langer Codes
- **Konsistent**: Gleiche Struktur für alle Kategorien
- **Nullen**: `{NUM:4}` sorgt für 0001, 0010, 0100

### Textvorlagen
- **Klar strukturiert**: Nutzen Sie Zeilenumbrüche
- **Vollständig**: Alle relevanten Informationen
- **Platzhalter**: Nutzen Sie Systemfelder für Datum, Ersteller

### Benachrichtigungen
- **Angemessene Vorlaufzeit**: 7-14 Tage für wichtige Lizenzen
- **Nicht zu früh**: Zu lange Vorlaufzeit wird ignoriert

### Archivierung
- **Regelmäßig aufräumen**: Sammellöschung alle 3-6 Monate
- **Aufbewahrungsfristen beachten**: Mindestens 90 Tage
