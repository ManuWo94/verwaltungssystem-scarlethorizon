    <footer class="footer mt-auto py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">Aktenverwaltungssystem - Department of Justice - Est. 1899</span>
                </div>
                <div class="col-md-6 text-right">
                    <span class="text-muted">Aktueller Benutzer: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Gast'); ?></span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Skripte wurden in den Header verschoben, hier bleiben nur die Initialisierungen -->
    <script>
        // Initialize Feather Icons
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });
    </script>
    <script src="<?php echo getBasePath(); ?>assets/js/scripts.js"></script>
    <script src="<?php echo getBasePath(); ?>assets/js/theme.js"></script>
    
    <!-- Zusätzliches Script für Bootstrap-Modals und Barrierefreiheit -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Direkt jQuery nutzen für Modal-Initialisierung
        if (typeof jQuery !== 'undefined') {
            // Hilfsfunktion zum Markieren der Seite mit inert außerhalb des Modals
            function setInertForPage(modal) {
                // Hauptelemente auswählen, die außerhalb des Modals liegen
                const elementsToInert = document.querySelectorAll('main, .container-fluid, #sidebarMenu, .navbar');
                
                elementsToInert.forEach(function(element) {
                    if (element && !modal.contains(element)) {
                        // inert-Attribut setzen
                        element.setAttribute('inert', '');
                        element.setAttribute('aria-hidden', 'true');
                        element.setAttribute('tabindex', '-1');
                    }
                });
            }
            
            // Hilfsfunktion zum Entfernen der inert-Attribute
            function removeInertFromPage() {
                const elementsWithInert = document.querySelectorAll('[inert], [aria-hidden="true"]');
                elementsWithInert.forEach(function(element) {
                    element.removeAttribute('inert');
                    element.removeAttribute('aria-hidden');
                    if (element.getAttribute('tabindex') === '-1') {
                        element.removeAttribute('tabindex');
                    }
                });
            }
            
            // Bootstrap-Modals direkt über jQuery initialisieren
            jQuery('[data-toggle="modal"]').on('click', function(e) {
                e.preventDefault();
                var target = jQuery(this).data('target');
                
                // Falls es ein # am Anfang gibt, entfernen
                if (target && target.startsWith('#')) {
                    target = target.substring(1);
                }
                
                // Alle modalen Dialoge schließen, um Fokus-Probleme zu vermeiden
                jQuery('.modal').modal('hide');
                
                // Timeout, damit die modalen Dialoge Zeit haben, sich zu schließen
                setTimeout(function() {
                    // Alle modals durchlaufen und nach der ID suchen
                    var found = false;
                    jQuery('.modal').each(function() {
                        var modalId = jQuery(this).attr('id');
                        if (modalId === target) {
                            var $modal = jQuery(this);
                            $modal.on('shown.bs.modal', function() {
                                // aria-hidden entfernen
                                $modal.removeAttr('aria-hidden');
                                
                                // Sicherstellen, dass das inert-Attribut korrekt gesetzt ist
                                setInertForPage($modal[0]);
                                
                                // Fokus auf das erste Formularfeld setzen, wenn vorhanden
                                var firstInput = $modal.find('input:visible, select:visible, textarea:visible, button:visible').not('[tabindex="-1"]').first();
                                if (firstInput.length > 0) {
                                    firstInput.focus();
                                } else {
                                    // Fallback: Fokus auf das erste Element mit tabindex
                                    var firstTabElement = $modal.find('[tabindex="0"]').first();
                                    if (firstTabElement.length > 0) {
                                        firstTabElement.focus();
                                    } else {
                                        // Letzter Fallback: Modal-Titel oder Header
                                        var modalTitle = $modal.find('.modal-title');
                                        if (modalTitle.length > 0) {
                                            modalTitle.attr('tabindex', '0').focus();
                                        }
                                    }
                                }
                            });
                            
                            // Modal beim Schließen aufräumen
                            $modal.on('hidden.bs.modal', function() {
                                // inert-Attribute von der Seite entfernen
                                removeInertFromPage();
                                
                                // Fokus auf das Element zurücksetzen, das das Modal geöffnet hat
                                setTimeout(function() {
                                    document.activeElement.blur();
                                    document.body.focus();
                                }, 10);
                            });
                            
                            // Modal zeigen
                            $modal.modal('show');
                            found = true;
                            return false; // Break the loop
                        }
                    });
                    
                    // Fallback für Modals mit Zeichen, die als ID-Selektoren problematisch sein könnten
                    if (!found) {
                        jQuery('.modal').each(function() {
                            var modalId = jQuery(this).attr('id') || '';
                            // Sicherstellen, dass beide Strings für den Vergleich normalisiert sind
                            if (modalId.replace(/[^a-zA-Z0-9]/g, '') === target.replace(/[^a-zA-Z0-9]/g, '')) {
                                var $modal = jQuery(this);
                                $modal.modal('show');
                                return false; // Break the loop
                            }
                        });
                    }
                }, 300);
            });
            
            // Direkter Klick auf Edit-Kategorie-Buttons 
            jQuery('.edit-category-btn').on('click', function(e) {
                e.preventDefault();
                var modalId = jQuery(this).data('modal-id');
                var category = jQuery(this).data('category');
                var department = jQuery(this).data('department');
                
                // Alle modalen Dialoge schließen
                jQuery('.modal').modal('hide');
                
                setTimeout(function() {
                    var $modal = jQuery('#' + modalId);
                    
                    // Event-Handler für das Anzeigen des Modals registrieren
                    $modal.on('shown.bs.modal', function() {
                        $modal.removeAttr('aria-hidden');
                        
                        // Inert-Attribut für den Rest der Seite setzen
                        setInertForPage($modal[0]);
                        
                        // Fokus auf das Input-Feld setzen
                        var input = $modal.find('input[name="new_category"]');
                        if (input.length) {
                            input.focus();
                        }
                    });
                    
                    // Event-Handler für das Schließen des Modals registrieren
                    $modal.on('hidden.bs.modal', function() {
                        // Inert-Attribut entfernen
                        removeInertFromPage();
                        
                        // Fokus zurücksetzen
                        setTimeout(function() {
                            document.activeElement.blur();
                            document.body.focus();
                        }, 10);
                    });
                    
                    // Modal anzeigen
                    $modal.modal('show');
                }, 300);
            });
            
            // Bei jedem Klick außerhalb des Modals sicherstellen, dass der Dialog geschlossen wird
            jQuery(document).on('click', '.modal', function(e) {
                if (e.target === this) {
                    jQuery(this).modal('hide');
                }
            });
        }
        
        // Konsolenausgabe für Debug-Zwecke
        console.log('Footer-Script geladen, Modals via jQuery initialisiert');
    });
    </script>
</body>
</html>
