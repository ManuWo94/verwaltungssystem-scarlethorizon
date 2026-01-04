/**
 * Department of Justice - Records Management System
 * Client-side JavaScript functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
    
    // Initialize tooltips for role badges
    initializeTooltips();
    
    // Setup event listeners
    setupFormValidation();
    setupDatatables();
    setupModals();
    setupDutyToggle();
    setupCalendarNavigation();
    setupFileHandling();
    setupTemplatesPage();
    
    // Handle any page-specific initialization
    if (document.querySelector('.cases-page')) {
        initializeCasesPage();
    } else if (document.querySelector('.calendar-page')) {
        initializeCalendarPage();
    } else if (document.querySelector('.files-page')) {
        initializeFilesPage();
    } else if (document.querySelector('.notes-page')) {
        initializeNotesPage();
    }
});

/**
 * Setup form validation for all forms
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Initialize DataTables for tables that need it
 */
function setupDatatables() {
    const dataTables = document.querySelectorAll('.data-table');
    
    if (dataTables.length > 0 && typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
        Array.from(dataTables).forEach(table => {
            $(table).DataTable({
                responsive: true,
                language: {
                    search: "Search records:",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                }
            });
        });
    }
}

/**
 * Setup Bootstrap modals
 */
function setupModals() {
    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('.btn-delete');
    
    Array.from(deleteButtons).forEach(button => {
        button.addEventListener('click', (event) => {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                event.preventDefault();
            }
        });
    });
    
    // Handle modal form submission
    const modalForms = document.querySelectorAll('.modal form');
    
    Array.from(modalForms).forEach(form => {
        form.addEventListener('submit', (event) => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Initialisiere alle Bootstrap-Modals
    if (typeof $ !== 'undefined') {
        // Initialisiere alle modalen Dialoge
        $('.modal').modal({
            show: false,
            keyboard: true
        });
        
        // Speziell für die Modalfenster bei Klageschriften
        // Fallback für die data-toggle-Attribute
        $('button[data-toggle="modal"]').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            $(target).modal('show');
        });
        
        // Explizite Initialisierung für die wichtigen Modalfenster
        // Gerichtsverhandlung terminieren Modal
        const scheduleCourtBtn = document.querySelector('[data-target="#scheduleCourtModal"]');
        if (scheduleCourtBtn) {
            scheduleCourtBtn.addEventListener('click', function() {
                $('#scheduleCourtModal').modal('show');
            });
        }
        
        // Urteil eintragen Modal
        const enterVerdictBtn = document.querySelector('[data-target="#enterVerdictModal"]');
        if (enterVerdictBtn) {
            enterVerdictBtn.addEventListener('click', function() {
                $('#enterVerdictModal').modal('show');
            });
        }
        
        // Initialisiere Bootstrap Accordions korrekt
        $('.accordion .btn-link').on('click', function(e) {
            // Pfeil-Icon drehen
            $(this).find('.fas.fa-chevron-down').toggleClass('rotate-180');
        });
    }
}

/**
 * Setup duty toggle functionality
 */
function setupDutyToggle() {
    const dutyToggle = document.querySelector('.duty-status-indicator form');
    
    // Keine Bestätigungsdialoge mehr erforderlich
    // Dienststatus wird sofort geändert, ohne nachzufragen
}

/**
 * Setup calendar navigation
 */
function setupCalendarNavigation() {
    const prevMonthBtn = document.querySelector('.calendar-prev-month');
    const nextMonthBtn = document.querySelector('.calendar-next-month');
    const monthSelect = document.querySelector('#month-select');
    const yearSelect = document.querySelector('#year-select');
    
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            if (monthSelect && yearSelect) {
                let month = parseInt(monthSelect.value);
                let year = parseInt(yearSelect.value);
                
                month--;
                if (month < 1) {
                    month = 12;
                    year--;
                }
                
                monthSelect.value = month;
                yearSelect.value = year;
                
                document.querySelector('.calendar-nav-form').submit();
            }
        });
    }
    
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            if (monthSelect && yearSelect) {
                let month = parseInt(monthSelect.value);
                let year = parseInt(yearSelect.value);
                
                month++;
                if (month > 12) {
                    month = 1;
                    year++;
                }
                
                monthSelect.value = month;
                yearSelect.value = year;
                
                document.querySelector('.calendar-nav-form').submit();
            }
        });
    }
    
    if (monthSelect) {
        monthSelect.addEventListener('change', function() {
            document.querySelector('.calendar-nav-form').submit();
        });
    }
    
    if (yearSelect) {
        yearSelect.addEventListener('change', function() {
            document.querySelector('.calendar-nav-form').submit();
        });
    }
}

/**
 * Setup file handling functionality
 */
function setupFileHandling() {
    // File upload preview
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    Array.from(fileInputs).forEach(input => {
        const preview = document.querySelector(`#${input.id}-preview`);
        
        if (preview) {
            input.addEventListener('change', function() {
                while (preview.firstChild) {
                    preview.removeChild(preview.firstChild);
                }
                
                const files = input.files;
                if (files.length > 0) {
                    const list = document.createElement('ul');
                    list.className = 'list-group mt-2';
                    
                    Array.from(files).forEach(file => {
                        const item = document.createElement('li');
                        item.className = 'list-group-item';
                        item.textContent = `${file.name} (${formatFileSize(file.size)})`;
                        list.appendChild(item);
                    });
                    
                    preview.appendChild(list);
                }
            });
        }
    });
    
    // Toggle folder contents
    const folderToggles = document.querySelectorAll('.folder-toggle');
    
    Array.from(folderToggles).forEach(toggle => {
        toggle.addEventListener('click', function() {
            const folderId = this.dataset.folderId;
            const folderContent = document.querySelector(`.folder-content[data-folder-id="${folderId}"]`);
            
            if (folderContent) {
                folderContent.classList.toggle('d-none');
                
                // Toggle icon
                const icon = this.querySelector('i');
                if (icon) {
                    if (icon.classList.contains('fa-folder')) {
                        icon.classList.remove('fa-folder');
                        icon.classList.add('fa-folder-open');
                    } else {
                        icon.classList.remove('fa-folder-open');
                        icon.classList.add('fa-folder');
                    }
                }
            }
        });
    });
    
    // Diese Funktionalität wird nun über direkte onclick-Methoden gesteuert
    // und muss nicht mehr hier initialisiert werden
}

/**
 * Initialize cases page functionality
 */
function initializeCasesPage() {
    // Calculate expiration date based on incident date
    const incidentDateInput = document.querySelector('#incident_date');
    const expirationDateInput = document.querySelector('#expiration_date');
    
    if (incidentDateInput && expirationDateInput) {
        incidentDateInput.addEventListener('change', function() {
            if (this.value) {
                // Calculate expiration date (21 days after incident date)
                const incidentDate = new Date(this.value);
                const expirationDate = new Date(incidentDate);
                expirationDate.setDate(expirationDate.getDate() + 21);
                
                // Format the date as YYYY-MM-DD for the input field
                const year = expirationDate.getFullYear();
                const month = String(expirationDate.getMonth() + 1).padStart(2, '0');
                const day = String(expirationDate.getDate()).padStart(2, '0');
                expirationDateInput.value = `${year}-${month}-${day}`;
            }
        });
    }
    
    // Filter cases by status
    const statusFilter = document.querySelector('#status-filter');
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            const url = new URL(window.location.href);
            
            if (this.value) {
                url.searchParams.set('status', this.value);
            } else {
                url.searchParams.delete('status');
            }
            
            window.location.href = url.toString();
        });
    }
}

/**
 * Initialize calendar page functionality
 */
function initializeCalendarPage() {
    // Add event functionality
    const addEventButtons = document.querySelectorAll('.add-event');
    
    Array.from(addEventButtons).forEach(button => {
        button.addEventListener('click', function() {
            const date = this.dataset.date;
            document.querySelector('#event_date').value = date;
            
            // Open modal
            $('#addEventModal').modal('show');
        });
    });
    
    // Edit event functionality
    const editEventButtons = document.querySelectorAll('.edit-event');
    
    Array.from(editEventButtons).forEach(button => {
        button.addEventListener('click', function() {
            const eventId = this.dataset.eventId;
            const title = this.dataset.title;
            const date = this.dataset.date;
            const time = this.dataset.time;
            const description = this.dataset.description;
            
            document.querySelector('#edit_event_id').value = eventId;
            document.querySelector('#edit_event_title').value = title;
            document.querySelector('#edit_event_date').value = date;
            document.querySelector('#edit_event_time').value = time;
            document.querySelector('#edit_event_description').value = description;
            
            // Open modal
            $('#editEventModal').modal('show');
        });
    });
}

/**
 * Initialize files page functionality
 */
function initializeFilesPage() {
    // Add folder functionality
    const addFolderForm = document.querySelector('#add-folder-form');
    
    if (addFolderForm) {
        addFolderForm.addEventListener('submit', function(event) {
            const folderName = document.querySelector('#folder_name').value.trim();
            
            if (!folderName) {
                event.preventDefault();
                alert('Please enter a folder name.');
            }
        });
    }
    
    // Add file functionality
    const addFileForm = document.querySelector('#add-file-form');
    
    if (addFileForm) {
        addFileForm.addEventListener('submit', function(event) {
            const fileTitle = document.querySelector('#file_title').value.trim();
            
            if (!fileTitle) {
                event.preventDefault();
                alert('Bitte geben Sie einen Titel für die Datei ein.');
            }
            
            // Wir prüfen nicht mehr auf fileContent, da Upload-Dateien kein Content brauchen
        });
    }
}

/**
 * Initialize notes page functionality
 */
function initializeNotesPage() {
    // Save note functionality
    const saveNoteButton = document.querySelector('#save-note');
    
    if (saveNoteButton) {
        saveNoteButton.addEventListener('click', function() {
            const noteTitle = document.querySelector('#note_title').value.trim();
            const noteContent = document.querySelector('#note_content').value.trim();
            
            if (!noteTitle || !noteContent) {
                alert('Please enter both a title and content for the note.');
                return;
            }
            
            document.querySelector('#save-note-form').submit();
        });
    }
}

/**
 * Initialize tooltips for role badges
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-toggle="tooltip"]'));
    if (typeof $ !== 'undefined' && typeof $.fn.tooltip !== 'undefined') {
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            $(tooltipTriggerEl).tooltip();
        });
    }
}

/**
 * Setup templates page functionality
 */
function setupTemplatesPage() {
    // Dynamic category filtering based on department selection
    const departmentSelect = document.getElementById('department-filter');
    const categorySelect = document.getElementById('category-filter');
    
    if (departmentSelect && categorySelect) {
        departmentSelect.addEventListener('change', function() {
            const selectedDepartment = this.value;
            
            // Reset category
            categorySelect.value = '';
            
            // Submit form to filter by department
            this.form.submit();
        });
    }
    
    // Template modals handling
    const editCategoryButtons = document.querySelectorAll('.edit-category-btn');
    if (editCategoryButtons.length > 0) {
        // Ensure proper initialization
        if (typeof $ !== 'undefined') {
            $(editCategoryButtons).on('click', function(e) {
                e.preventDefault();
                const department = $(this).data('department');
                const category = $(this).data('category');
                const modalId = $(this).data('modal-id');
                
                // Alle modalen Dialoge entladen, falls noch einer aktiv sein sollte
                $('.modal').modal('hide');
                
                // Sicherstellen, dass der Fokus richtig gesetzt wird
                setTimeout(function() {
                    // Modalen Dialog anzeigen
                    $(`#${modalId}`).on('shown.bs.modal', function() {
                        // Fokus auf das Eingabefeld setzen
                        $(`#new_category_${category.toString().replace(/[^a-z0-9]/gi, '')}`).focus();
                    });
                    
                    $(`#${modalId}`).modal('show');
                }, 100);
            });
            
            // Bei Schließen der Dialoge Fokus auf den Body zurücksetzen
            $('.modal').on('hidden.bs.modal', function() {
                // Sicherstellen, dass der Fokus nicht im Dialog bleibt
                document.activeElement.blur();
                document.querySelector('body').focus();
            });
            
            // Bei Klick außerhalb des Dialogs den Dialog schließen
            $('.modal').on('click', function(e) {
                if ($(e.target).hasClass('modal')) {
                    $(this).modal('hide');
                }
            });
            
            // ARIA-Attribute für alle Modalen Dialoge korrekt setzen
            $('.modal').on('show.bs.modal', function() {
                $(this).removeAttr('aria-hidden');
                
                // Statt aria-hidden das inert-Attribut für den Rest der Seite setzen
                document.querySelectorAll('main, header, footer, nav').forEach(function(element) {
                    if (element) {
                        element.setAttribute('inert', '');
                    }
                });
            });
            
            // Beim Schließen des Modals das inert-Attribut entfernen
            $('.modal').on('hide.bs.modal', function() {
                document.querySelectorAll('[inert]').forEach(function(element) {
                    element.removeAttribute('inert');
                });
            });
        }
    }
    
    // Template creation form handling for department-based category filtering
    const addTemplateModal = document.getElementById('addTemplateModal');
    if (addTemplateModal) {
        const templateDepartmentSelect = document.getElementById('template_department');
        const templateCategorySelect = document.getElementById('template_category');
        const newCategoryGroup = document.getElementById('new_category_group');
        
        // Filter categories based on department
        if (templateDepartmentSelect && templateCategorySelect) {
            templateDepartmentSelect.addEventListener('change', function() {
                const selectedDepartment = this.value;
                
                // Hide all options first
                Array.from(templateCategorySelect.options).forEach(option => {
                    if (option.value === 'new') {
                        option.style.display = '';  // Always show "Add new" option
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // Show options for selected department
                Array.from(templateCategorySelect.options).forEach(option => {
                    if (option.dataset.department === selectedDepartment) {
                        option.style.display = '';
                    }
                });
                
                // Select first visible option if current is hidden
                const visibleOptions = Array.from(templateCategorySelect.options).filter(
                    opt => opt.style.display !== 'none'
                );
                if (visibleOptions.length > 0) {
                    templateCategorySelect.value = visibleOptions[0].value;
                }
            });
        }
        
        // Show/hide new category input based on template category selection
        if (templateCategorySelect && newCategoryGroup) {
            templateCategorySelect.addEventListener('change', function() {
                if (this.value === 'new') {
                    newCategoryGroup.classList.remove('d-none');
                } else {
                    newCategoryGroup.classList.add('d-none');
                }
            });
        }
    }
    
    // Same for edit modal
    const editTemplateModal = document.getElementById('editTemplateModal');
    if (editTemplateModal) {
        const editTemplateDepartmentSelect = document.getElementById('edit_template_department');
        const editTemplateCategorySelect = document.getElementById('edit_template_category');
        const editNewCategoryGroup = document.getElementById('edit_new_category_group');
        
        // Filter categories based on department
        if (editTemplateDepartmentSelect && editTemplateCategorySelect) {
            editTemplateDepartmentSelect.addEventListener('change', function() {
                const selectedDepartment = this.value;
                
                // Hide all options first
                Array.from(editTemplateCategorySelect.options).forEach(option => {
                    if (option.value === 'new') {
                        option.style.display = '';  // Always show "Add new" option
                    } else {
                        option.style.display = 'none';
                    }
                });
                
                // Show options for selected department
                Array.from(editTemplateCategorySelect.options).forEach(option => {
                    if (option.dataset.department === selectedDepartment) {
                        option.style.display = '';
                    }
                });
                
                // Select first visible option if current is hidden
                const visibleOptions = Array.from(editTemplateCategorySelect.options).filter(
                    opt => opt.style.display !== 'none'
                );
                if (visibleOptions.length > 0) {
                    editTemplateCategorySelect.value = visibleOptions[0].value;
                }
            });
        }
        
        // Show/hide new category input based on template category selection
        if (editTemplateCategorySelect && editNewCategoryGroup) {
            editTemplateCategorySelect.addEventListener('change', function() {
                if (this.value === 'new') {
                    editNewCategoryGroup.classList.remove('d-none');
                } else {
                    editNewCategoryGroup.classList.add('d-none');
                }
            });
        }
    }
}

/**
 * Format file size in a human-readable format
 * 
 * @param {number} bytes File size in bytes
 * @returns {string} Formatted file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Neue Funktion zum Umschalten der Ausrüstungs-Akkordeonkategorie
 * Diese Funktion wird direkt vom onclick-Attribut in der equipment.php aufgerufen
 * 
 * @param {string} typeId Die ID des zu öffnenden/schließenden Typs
 */
function toggleEquipmentCategory(typeId) {
    // Finde das Content-Element und das Icon
    const contentElement = document.getElementById('collapse_' + typeId);
    const iconElement = document.getElementById('icon_' + typeId);
    
    if (contentElement && iconElement) {
        // Toggle die Anzeige
        if (contentElement.classList.contains('show')) {
            contentElement.classList.remove('show');
            contentElement.classList.add('hide');
            iconElement.classList.remove('rotate-180');
        } else {
            contentElement.classList.remove('hide');
            contentElement.classList.add('show');
            iconElement.classList.add('rotate-180');
        }
    }
}
