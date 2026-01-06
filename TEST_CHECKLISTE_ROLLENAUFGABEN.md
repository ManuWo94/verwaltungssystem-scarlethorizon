# Test-Checkliste für rollenbasierte Aufgabenverwaltung

## Vorbereitung
- [ ] Backup der aktuellen task_assignments.php wurde erstellt
- [ ] PHP Syntax-Check erfolgreich
- [ ] Datei-Berechtigungen korrekt

## Funktionstest - Aufgabe erstellen

### Test 1: Benutzeraufgabe erstellen
- [ ] Modal öffnet sich
- [ ] "Benutzer zuweisen" ist vorausgewählt
- [ ] Benutzerliste wird angezeigt
- [ ] Aufgabe kann erstellt werden
- [ ] Benutzer erhält Benachrichtigung
- [ ] Aufgabe erscheint in der Liste

### Test 2: Rollenaufgabe erstellen
- [ ] "Rolle zuweisen" auswählbar
- [ ] Benutzerauswahl wird versteckt
- [ ] Rollenliste wird angezeigt
- [ ] Aufgabe kann erstellt werden
- [ ] Alle Benutzer mit der Rolle erhalten Benachrichtigung
- [ ] Aufgabe erscheint mit "(Rolle)" Markierung
- [ ] Status zeigt "Noch nicht beansprucht"

## Funktionstest - Aufgabe beanspruchen

### Test 3: Rollenaufgabe beanspruchen (berechtigter Benutzer)
- [ ] "Beanspruchen" Button ist sichtbar
- [ ] Bestätigungsdialog erscheint
- [ ] Beanspruchung wird gespeichert
- [ ] Status ändert sich zu "Beansprucht von: [Name]"
- [ ] Button wechselt zu "Freigeben"
- [ ] Andere Benutzer sehen, wer beansprucht hat

### Test 4: Rollenaufgabe beanspruchen (nicht berechtigter Benutzer)
- [ ] "Beanspruchen" Button ist NICHT sichtbar
- [ ] Benutzer ohne Rolle kann Aufgabe nicht beanspruchen

### Test 5: Bereits beanspruchte Aufgabe
- [ ] Andere Benutzer sehen keinen "Beanspruchen" Button mehr
- [ ] Anzeige zeigt den Beanspruchenden

## Funktionstest - Aufgabe freigeben

### Test 6: Eigene Beanspruchung aufheben
- [ ] "Freigeben" Button ist sichtbar
- [ ] Bestätigungsdialog erscheint
- [ ] Beanspruchung wird entfernt
- [ ] Status ändert zu "Noch nicht beansprucht"
- [ ] Button wechselt zu "Beanspruchen"

### Test 7: Admin hebt Beanspruchung auf
- [ ] Admin sieht "Freigeben" Button bei fremder Beanspruchung
- [ ] Admin kann Beanspruchung aufheben
- [ ] Aufgabe wird freigegeben

## Funktionstest - Aufgabe abhaken

### Test 8: Rollenaufgabe abhaken (ohne Beanspruchung)
- [ ] Checkbox ist anklickbar
- [ ] Fehlermeldung erscheint: "müssen Sie die Aufgabe zuerst beanspruchen"
- [ ] Status ändert sich NICHT

### Test 9: Rollenaufgabe abhaken (mit Beanspruchung)
- [ ] Benutzer beansprucht Aufgabe
- [ ] Checkbox ist anklickbar
- [ ] Aufgabe wird als erledigt markiert
- [ ] "Erledigt von" wird gespeichert
- [ ] Zeitstempel wird gesetzt

### Test 10: Benutzeraufgabe abhaken
- [ ] Zugewiesener Benutzer kann abhaken
- [ ] Status ändert sich zu "Erledigt"
- [ ] "Erledigt von" wird gespeichert

## Funktionstest - Kommentare

### Test 11: Kommentar von beliebigem Benutzer
- [ ] Benutzer öffnet fremde Aufgabe
- [ ] Kommentarfeld ist sichtbar und editierbar
- [ ] Kommentar kann hinzugefügt werden
- [ ] Kommentar erscheint in der Liste
- [ ] Ersteller und Zugewiesener erhalten Benachrichtigung

### Test 12: Kommentare bei Rollenaufgaben
- [ ] Alle können Rollenaufgaben kommentieren
- [ ] Kommentare werden korrekt angezeigt
- [ ] Benachrichtigungen werden versendet

## Funktionstest - Löschen

### Test 13: Löschen durch Ersteller
- [ ] Ersteller sieht Löschen-Button
- [ ] Bestätigungsdialog erscheint
- [ ] Aufgabe wird gelöscht

### Test 14: Löschen durch Admin
- [ ] Admin sieht Löschen-Button bei fremden Aufgaben
- [ ] Löschung funktioniert

### Test 15: Löschen durch zugewiesenen Benutzer (nicht Ersteller)
- [ ] Löschen-Button ist NICHT sichtbar
- [ ] Benutzer kann Aufgabe nicht löschen

## Funktionstest - Sichtbarkeit

### Test 16: Rollenaufgaben-Sichtbarkeit
- [ ] Benutzer mit Rolle sieht die Aufgabe
- [ ] Benutzer ohne Rolle sieht die Aufgabe NICHT
- [ ] Ersteller sieht seine erstellten Aufgaben
- [ ] Admins sehen alle Aufgaben

### Test 17: Alle Aufgaben ansehen
- [ ] Jeder kann alle Aufgaben in der Liste sehen
- [ ] Details-Ansicht funktioniert für alle Aufgaben
- [ ] Kommentieren ist überall möglich

## Funktionstest - UI/UX

### Test 18: Rollenaufgaben-Kennzeichnung
- [ ] "(Rolle)" erscheint bei Rollennamen
- [ ] Beanspruchungsstatus wird angezeigt
- [ ] Farbcodierung funktioniert

### Test 19: Formular-Umschaltung
- [ ] Radio-Buttons funktionieren
- [ ] Benutzer-/Rollendropdown wechselt korrekt
- [ ] Required-Validierung funktioniert

### Test 20: Benachrichtigungen
- [ ] Badge-Counter werden aktualisiert
- [ ] Benachrichtigungen enthalten korrekte Links
- [ ] Benachrichtigungstext ist aussagekräftig

## Edge Cases

### Test 21: Gleichzeitige Beanspruchung
- [ ] Zwei Benutzer versuchen gleichzeitig zu beanspruchen
- [ ] Nur einer erfolgreich
- [ ] Zweiter erhält Fehlermeldung

### Test 22: Benutzer verliert Rolle
- [ ] Benutzer mit beanspruchter Aufgabe
- [ ] Rolle wird entfernt
- [ ] Beanspruchung bleibt bestehen (oder Admin kann freigeben)

### Test 23: Rolle wird gelöscht
- [ ] Aufgaben mit gelöschter Rolle
- [ ] System bleibt stabil
- [ ] Keine fatalen Fehler

## Performance

### Test 24: Viele Aufgaben
- [ ] Liste mit 50+ Aufgaben lädt
- [ ] Filterung funktioniert schnell
- [ ] Keine Timeout-Fehler

### Test 25: Viele Kommentare
- [ ] Aufgabe mit 20+ Kommentaren
- [ ] Laden funktioniert
- [ ] Scrollen ist flüssig

## Regressionstests

### Test 26: Bestehende Benutzeraufgaben
- [ ] Alte Aufgaben (ohne assignment_type) funktionieren
- [ ] Werden als Benutzeraufgaben behandelt
- [ ] Alle Funktionen arbeiten korrekt

### Test 27: Weiterleiten
- [ ] Weiterleitungsfunktion bleibt intakt
- [ ] Funktioniert mit Rollenaufgaben
- [ ] Funktioniert mit Benutzeraufgaben

### Test 28: Kategorien
- [ ] Kategorieverwaltung funktioniert
- [ ] Zuordnung zu Aufgaben klappt
- [ ] Filterung nach Kategorie funktioniert

## Browser-Kompatibilität
- [ ] Chrome/Edge
- [ ] Firefox
- [ ] Safari (falls verfügbar)

## Status
- Datum: _________________
- Tester: _________________
- Ergebnis: PASS / FAIL
- Anmerkungen: _________________
