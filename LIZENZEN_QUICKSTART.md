# Lizenzverwaltung - Schnellstart

## 🚀 Sofort einsatzbereit!

Das System ist vollständig implementiert und kann direkt genutzt werden.

## 📁 Neue Dateien

### Module (Frontend)
- `modules/licenses.php` - Hauptmodul: Lizenzen anzeigen, erstellen, erneuern
- `modules/license_categories.php` - Kategorienverwaltung (nur Admins)
- `modules/license_archive.php` - Archiv mit Sammellöschung

### Daten
- `data/licenses.json` - Alle Lizenzen (leer)
- `data/license_categories.json` - 3 Beispielkategorien vorinstalliert

### Automatisierung
- `license_cron.php` - Automatische Archivierung & Benachrichtigungen

### Dokumentation
- `LIZENZVERWALTUNG_DOKUMENTATION.md` - Vollständige Anleitung

## ✅ Features

### ✨ Übersichtlich
- **Dashboard**: Statistiken, Filter, Suchfunktion
- **Zweistufige Erstellung**: Kategorie → Daten
- **Status-Badges**: Auf einen Blick sehen was abläuft

### 🔢 Automatische Nummerierung
- Schema frei konfigurierbar: `BL-{YEAR}-{NUM:4}`
- Vorschau vor Erstellung
- Fortlaufende Zählung pro Kategorie

### 📝 Flexible Vorlagen
- Platzhalter-System für Textgenerierung
- Benutzerdefinierte Felder pro Kategorie
- Automatische Signatur mit Rolle

### ⏰ Benachrichtigungen
- Optional aktivierbar
- Konfigurierbare Vorwarnzeit (1-365 Tage)
- Nutzt bestehendes Benachrichtigungssystem

### 📦 Archiv
- Automatische Archivierung abgelaufener Lizenzen
- Einzellöschung oder Massenauswahl
- Zeitbasierte Sammellöschung (30/60/90/180/365 Tage)
- Reaktivierung möglich

### 🔄 Lizenz erneuern
- Erstellt neue Lizenz mit allen Daten
- Neue Nummer und Laufzeit
- Alte Lizenz bleibt im Archiv
- Ein Klick!

## 🎯 Erste Schritte

### 1. Navigation öffnen
In der Sidebar → **Lizenzverwaltung** → **Lizenzen**

### 2. Erste Lizenz erstellen
1. Button "Neue Lizenz" klicken
2. Kategorie wählen (z.B. "Business License")
3. Daten eingeben
4. Laufzeit prüfen (Standard: 365 Tage)
5. Optional: Benachrichtigung aktivieren
6. "Lizenz erstellen" klicken

### 3. Lizenz anzeigen
- 👁️-Button klicken
- Text wird angezeigt
- "Text kopieren" nutzen

### 4. Lizenz erneuern
- 🔄-Button klicken
- Daten werden übernommen
- Anpassen und erstellen

## 🔧 Kategorien anpassen (Admin)

Sidebar → **Lizenzverwaltung** → **Kategorien**

### Neue Kategorie erstellen
1. "Neue Kategorie" klicken
2. Name eingeben (z.B. "Pilot License")
3. Nummern-Schema: `PL-{YEAR}-{NUM:4}`
4. Laufzeit: 1095 Tage (3 Jahre)
5. Felder hinzufügen:
   - Feldname: `HOLDER_NAME`, Label: "Inhabername"
   - Feldname: `AIRCRAFT_TYPE`, Label: "Flugzeugtyp"
6. Textvorlage mit Platzhaltern
7. Speichern

## ⚙️ Automatisierung einrichten

### Cron-Job (empfohlen)
```bash
# Täglich um 01:00 Uhr ausführen
0 1 * * * /usr/bin/php /pfad/zu/license_cron.php
```

**Oder manuell:**
```bash
php license_cron.php
```

**Macht:**
- Archiviert abgelaufene Lizenzen
- Sendet Ablaufbenachrichtigungen

## 📋 Vorinstallierte Kategorien

| Kategorie | Schema | Laufzeit |
|-----------|--------|----------|
| Business License | BL-{YEAR}-{NUM:4} | 365 Tage |
| Weapon License | WL-{YEAR}-{NUM:4} | 730 Tage |
| Driver's License | DL-{YEAR}-{NUM:5} | 1825 Tage |

## 💡 Tipps

### Bedienung
- **Filter nutzen**: Kategorie + Suche kombinierbar
- **Archiv regelmäßig aufräumen**: Alle 3 Monate Sammellöschung
- **Benachrichtigungen**: 7-14 Tage Vorlauf empfohlen

### UI-Flow
```
Dashboard → Neue Lizenz → Kategorie → Daten → ✓ Erstellt
          → Lizenz ansehen → 👁️ → Text kopieren
          → Erneuern → 🔄 → Daten prüfen → ✓ Neue Lizenz
          → Archiv → Auswählen → 🗑️ Löschen
```

### Kategorien
- **Eindeutige Schemas**: Jede Kategorie eigenes Präfix (BL, WL, DL)
- **Sinnvolle Laufzeiten**: Business = 1 Jahr, Weapon = 2 Jahre
- **Pflichtfelder markieren**: `required: true`

## 🎨 Benutzerführung

### Kritische Aktionen = Bestätigung
- Lizenz löschen: ✅ Bestätigung
- Sammellöschung: ✅ Bestätigung
- Kategorie deaktivieren: ✅ Bestätigung

### Häufige Aktionen = Direkt
- Lizenz ansehen: 👁️ Direkt
- Lizenz erneuern: 🔄 Direkt zum Modal
- Text kopieren: 📋 Ein Klick

### Visuelle Hinweise
- 🟢 Grün = Aktiv
- 🟡 Gelb = Läuft in 8-14 Tagen ab
- 🔴 Rot = Läuft in ≤7 Tagen ab oder abgelaufen

## 📊 Dashboard-Elemente

```
╔════════════════════════════════════╗
║ Statistiken                        ║
║ [42] Aktiv | [3] Kategorien       ║
║ [5] Bald ab | [128] Archiviert    ║
╠════════════════════════════════════╣
║ Filter                             ║
║ Kategorie: [Alle ▼]               ║
║ Suche: [____________]              ║
╠════════════════════════════════════╣
║ Lizenzen-Tabelle                  ║
║ Nr. | Kategorie | Inhaber | ...   ║
╚════════════════════════════════════╝
```

## 🔐 Berechtigungen

| Rolle | View | Create | Delete | Categories |
|-------|------|--------|--------|------------|
| User | ✅ | ✅ | ❌ | ❌ |
| Staff | ✅ | ✅ | ✅ | ❌ |
| Admin | ✅ | ✅ | ✅ | ✅ |

## 📞 Hilfe

Siehe `LIZENZVERWALTUNG_DOKUMENTATION.md` für:
- Detaillierte Platzhalter-Referenz
- Datenstruktur-Schema
- Erweiterte Konfiguration
- Workflow-Diagramme

## ✨ Das System ist bereit!

Alle Funktionen sind implementiert und getestet. Viel Erfolg! 🎉
