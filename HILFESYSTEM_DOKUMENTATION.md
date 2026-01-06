# Hilfe- und Dokumentationssystem

## 📚 Übersicht

Das Hilfe- und Dokumentationssystem bietet eine integrierte, durchsuchbare Wissensdatenbank für alle Funktionen der Anwendung. Benutzer können Artikel lesen, durchsuchen und nach Kategorien filtern. Administratoren können Artikel und Kategorien verwalten und bearbeiten.

## ✨ Hauptfunktionen

### Für alle Benutzer:
- **📖 Artikel lesen:** Umfassende Anleitungen zu allen Systemfunktionen
- **🔍 Suchfunktion:** Echtzeit-Suche durch Titel und Keywords
- **📁 Kategorisierung:** Artikel in 6 thematischen Kategorien organisiert
- **🔗 Verwandte Artikel:** Navigation zu ähnlichen Themen
- **🧭 Breadcrumb-Navigation:** Immer wissen, wo Sie sich befinden

### Für Administratoren:
- **✏️ Artikel erstellen/bearbeiten:** Volle WYSIWYG-ähnliche Bearbeitung mit HTML
- **📂 Kategorien verwalten:** Eigene Kategorien erstellen und anpassen
- **🖼️ Bilder hochladen:** Screenshots und Illustrationen einfügen
- **📝 Entwürfe:** Artikel als Entwurf speichern vor Veröffentlichung
- **🏷️ Keywords:** Suchoptimierung durch Schlagwörter

## 📂 Dateistruktur

```
modules/
├── help.php                      # Hauptmodul (Benutzeransicht)
└── help_admin.php                # Admin-Panel für Artikelverwaltung

data/
├── help_articles.json            # Alle Artikel (20 vorinstalliert)
└── help_categories.json          # Kategorien (6 Standard)

uploads/
└── help_images/                  # Hochgeladene Bilder für Artikel
    └── .gitkeep
```

## 🎯 Vorinstallierte Kategorien

| ID | Name | Icon | Beschreibung |
|----|------|------|--------------|
| `cat_getting_started` | Erste Schritte | play-circle | Grundlegende Funktionen und Einstieg |
| `cat_cases` | Aktenverwaltung | folder | Fälle, Angeklagte, Klageschriften |
| `cat_licenses` | Lizenzverwaltung | award | Lizenzen erstellen, verwalten und archivieren |
| `cat_tasks` | Aufgaben & Organisation | check-square | Aufgaben, Kalender, Notizen |
| `cat_office` | Büroverwaltung | briefcase | Personal, Ausrüstung, Beschlagnahmungen |
| `cat_admin` | Administration | settings | Systemverwaltung, Benutzer, Rollen |

## 📄 Vorinstallierte Artikel (20)

### Erste Schritte (4 Artikel)
1. **Willkommen im Verwaltungssystem** - Einführung und Übersicht
2. **Anmelden und erste Schritte** - Login, Dashboard, Passwort
3. **Benachrichtigungen** - Benachrichtigungssystem nutzen
4. **Such- und Filterfunktionen** - Effizient suchen und filtern

### Aktenverwaltung (4 Artikel)
5. **Einen neuen Fall erstellen** - Schritt-für-Schritt Anleitung
6. **Fälle bearbeiten und verwalten** - Status ändern, Dokumente
7. **Angeklagte verwalten** - Personen zu Fällen hinzufügen
8. **Klageschriften erstellen** - Anklagen und PDF-Export

### Lizenzverwaltung (4 Artikel)
9. **Lizenzen erstellen** - Zweistufiger Erstellungsprozess
10. **Lizenzen erneuern und verwalten** - Verlängern, Benachrichtigungen
11. **Lizenzarchiv verwalten** - Archivierte Lizenzen bereinigen
12. **Lizenzkategorien verwalten (Admin)** - Kategorien konfigurieren

### Aufgaben & Organisation (2 Artikel)
13. **Aufgaben verwalten** - To-Do-Listen, Status, Filter
14. **Kalender und Termine** - Termine planen, Ansichten

### Büroverwaltung (2 Artikel)
15. **Personalverwaltung** - Mitarbeiter, Dienstzeiten
16. **Ausrüstung verwalten** - Inventar, Zuweisung, Wartung

### Administration (2 Artikel)
17. **Benutzerverwaltung (Admin)** - User anlegen, Rollen zuweisen
18. **Rollenverwaltung (Admin)** - Berechtigungen konfigurieren

## 🔧 Benutzung für Administratoren

### Neuen Artikel erstellen

1. Navigieren Sie zu **Hilfe bearbeiten** (Sidebar)
2. Klicken Sie auf **Neuer Artikel**
3. Füllen Sie das Formular aus:
   - **Titel:** Aussagekräftiger Titel
   - **Kategorie:** Thematische Zuordnung
   - **Keywords:** Komma-getrennte Suchbegriffe
   - **Inhalt:** HTML-formatierter Text
   - **Veröffentlichen:** Haken für sofortige Veröffentlichung

### HTML-Editor Toolbar

Die Toolbar bietet Formatierungshilfen:

| Button | Funktion | HTML-Code |
|--------|----------|-----------|
| **H2** | Überschrift 2 | `<h2>Text</h2>` |
| **H3** | Überschrift 3 | `<h3>Text</h3>` |
| **B** | Fettschrift | `<strong>Text</strong>` |
| **I** | Kursiv | `<em>Text</em>` |
| **• Liste** | Aufzählung | `<ul><li>Punkt</li></ul>` |
| **1. Liste** | Nummerierung | `<ol><li>Punkt</li></ol>` |
| **Code** | Inline-Code | `<code>Code</code>` |
| **Zitat** | Hervorgehobener Block | `<blockquote>Text</blockquote>` |
| **🖼️ Bild** | Bild hochladen | `<img src="..." />` |

### Bilder hochladen

1. Klicken Sie im Editor auf **🖼️ Bild**
2. Wählen Sie eine Bilddatei (JPG, PNG, GIF)
3. Klicken Sie auf **Hochladen**
4. Das Bild wird automatisch in den Artikel eingefügt

**Speicherort:** `/uploads/help_images/help_[uniqid].[ext]`

### Artikel bearbeiten

1. Klicken Sie auf das **Bearbeiten-Symbol** (✏️) bei einem Artikel
2. Ändern Sie die gewünschten Felder
3. Speichern Sie die Änderungen
4. Das Feld `updated_at` wird automatisch aktualisiert

### Kategorien verwalten

#### Neue Kategorie erstellen:

```json
{
  "id": "cat_custom",           // Eindeutige ID
  "name": "Meine Kategorie",    // Anzeigename
  "icon": "folder",             // Feather Icon Name
  "description": "Beschreibung",
  "order": 7                    // Sortierung
}
```

**Feather Icons:** Siehe https://feathericons.com/

#### Kategorie bearbeiten:
1. Wechseln Sie zum Tab **Kategorien**
2. Klicken Sie auf **Bearbeiten** (✏️)
3. Passen Sie Name, Icon, Beschreibung oder Reihenfolge an

#### Kategorie löschen:
⚠️ Nur möglich, wenn keine Artikel in der Kategorie sind!

## 🔍 Suchfunktion

### Wie die Suche funktioniert

1. **Echtzeit-Suche:** Ergebnisse während der Eingabe
2. **Durchsuchte Felder:**
   - Artikeltitel
   - Keywords
3. **Case-insensitive:** Groß-/Kleinschreibung egal
4. **Auto-Expand:** Kategorien mit Treffern werden automatisch aufgeklappt

### Suchbeispiele

| Suchbegriff | Findet Artikel über... |
|-------------|------------------------|
| `lizenz` | Lizenzverwaltung, Kategorien, Archiv |
| `erstellen` | Erstellen von Fällen, Lizenzen, Aufgaben |
| `admin` | Benutzer-, Rollenverwaltung |
| `pdf` | PDF-Export von Klageschriften |

## 🎨 Styling-Hinweise

### Artikel-Formatierung

```html
<!-- Überschriften -->
<h2>Hauptüberschrift im Artikel</h2>
<h3>Unterüberschrift</h3>

<!-- Listen -->
<ul>
  <li>Aufzählungspunkt</li>
  <li>Weiterer Punkt</li>
</ul>

<ol>
  <li>Nummerierter Punkt 1</li>
  <li>Nummerierter Punkt 2</li>
</ol>

<!-- Hervorhebungen -->
<strong>Wichtiger Text</strong>
<em>Betonter Text</em>
<code>Inline-Code</code>

<!-- Info-Box -->
<blockquote>
  Wichtiger Hinweis oder Tipp
</blockquote>

<!-- Bilder -->
<img src="/uploads/help_images/bild.png" alt="Beschreibung" />
```

### CSS-Klassen (automatisch angewendet)

- Bilder: `border-radius: 8px`, `box-shadow`, responsive
- Code-Blöcke: Grauer Hintergrund, linke Akzent-Linie
- Blockquotes: Blaue Linie, hellblauer Hintergrund
- Links: Automatische Link-Formatierung

## 📊 Datenstruktur

### Artikel-Objekt (`help_articles.json`)

```json
{
  "id": "art_example",
  "title": "Artikeltitel",
  "category_id": "cat_getting_started",
  "content": "<h2>HTML-Inhalt</h2><p>Text...</p>",
  "keywords": ["Keyword1", "Keyword2"],
  "related_articles": ["art_other1", "art_other2"],
  "author": "Admin",
  "created_at": "2026-01-06 10:00:00",
  "updated_at": "2026-01-06 12:00:00",
  "published": true
}
```

### Kategorie-Objekt (`help_categories.json`)

```json
{
  "id": "cat_example",
  "name": "Kategoriename",
  "icon": "folder",
  "order": 1,
  "description": "Kurzbeschreibung"
}
```

## 🔄 AJAX-Operationen

### Artikel-Operationen

| Action | Parameter | Beschreibung |
|--------|-----------|--------------|
| `create_article` | title, category_id, content, keywords, published | Neuen Artikel erstellen |
| `update_article` | article_id, [alle Felder] | Artikel aktualisieren |
| `delete_article` | article_id | Artikel löschen |

### Kategorie-Operationen

| Action | Parameter | Beschreibung |
|--------|-----------|--------------|
| `create_category` | name, icon, description, order | Kategorie erstellen |
| `update_category` | category_id, [alle Felder] | Kategorie bearbeiten |
| `delete_category` | category_id | Kategorie löschen (nur wenn leer) |

### Bild-Upload

| Action | Parameter | Beschreibung |
|--------|-----------|--------------|
| `upload_image` | image (File) | Bild hochladen, gibt URL zurück |

## 🛡️ Berechtigungen

### Zugriff auf Hilfe-Module

| Modul | Berechtigung | Beschreibung |
|-------|--------------|--------------|
| `help.php` | Alle eingeloggten Benutzer | Artikel lesen und durchsuchen |
| `help_admin.php` | `admin/view` ODER `Administrator` | Artikel und Kategorien verwalten |

### Sicherheit

- ✅ Alle Eingaben werden mit `htmlspecialchars()` escaped
- ✅ File-Upload nur für Bilder (Image MIME-Type)
- ✅ Eindeutige Dateinamen durch `uniqid()`
- ✅ Admin-Check bei allen Schreiboperationen

## 🚀 Erweitungsmöglichkeiten

### Zusätzliche Features (optional)

1. **Video-Einbettung:**
   - YouTube/Vimeo iframes in Artikel
   - Tutorial-Videos

2. **PDF-Export:**
   - Artikel als PDF herunterladen
   - Offline-Dokumentation

3. **Versionierung:**
   - Artikel-Versionshistorie
   - Änderungen nachverfolgen

4. **Feedback-System:**
   - "War dieser Artikel hilfreich?" Button
   - Kommentare zu Artikeln

5. **Mehrsprachigkeit:**
   - Artikel in mehreren Sprachen
   - Sprachumschalter

6. **Statistiken:**
   - Meist gelesene Artikel
   - Suchbegriff-Analyse

## 📝 Best Practices

### Artikel schreiben

✅ **DO:**
- Klare, verständliche Sprache
- Schritt-für-Schritt Anleitungen mit Nummerierung
- Screenshots zur Veranschaulichung
- Verwandte Artikel verlinken
- Keywords für bessere Auffindbarkeit

❌ **DON'T:**
- Zu technische Fachbegriffe ohne Erklärung
- Lange Textwüsten ohne Absätze
- Veraltete Screenshots
- Fehlende Überschriften-Struktur

### Kategorien organisieren

- Maximal 8-10 Kategorien für Übersichtlichkeit
- Logische Gruppierung nach Funktionsbereichen
- Aussagekräftige Icons (Feather Icons)
- Beschreibungen unter 50 Zeichen

### Keywords wählen

- 3-7 Keywords pro Artikel
- Häufige Suchbegriffe verwenden
- Synonyme einbeziehen
- Variationen (Singular/Plural)

## 🐛 Troubleshooting

### Problem: Bilder werden nicht angezeigt

**Lösung:**
1. Prüfen Sie Upload-Berechtigungen: `chmod 777 uploads/help_images/`
2. Verifizieren Sie den Pfad in `<img src="...">`
3. Überprüfen Sie Browser-Console auf 404-Fehler

### Problem: Artikel-Suche findet nichts

**Lösung:**
1. Keywords korrekt gesetzt?
2. JavaScript-Fehler in Browser-Console?
3. Cache leeren und Seite neu laden

### Problem: Kategorie kann nicht gelöscht werden

**Lösung:**
- Kategorie enthält noch Artikel
- Verschieben Sie alle Artikel in andere Kategorien
- Dann erneut löschen

### Problem: Editor-Toolbar funktioniert nicht

**Lösung:**
1. jQuery geladen? Prüfen Sie Console
2. JavaScript-Fehler beheben
3. Browser-Kompatibilität (moderne Browser erforderlich)

## 📞 Support

Bei Problemen oder Fragen:
1. Durchsuchen Sie diese Dokumentation
2. Prüfen Sie die vorinstallierten Beispiel-Artikel
3. Kontaktieren Sie den System-Administrator

---

**Version:** 1.0  
**Erstellt:** 06.01.2026  
**Letzte Aktualisierung:** 06.01.2026
