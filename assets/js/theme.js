/**
 * Department of Justice - Records Management System
 * Theme Management Functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    // Theme-Verwaltung: Initialisierung
    setupThemeToggle();
    enhanceAccordions();
});

/**
 * Richtet den Theme-Umschalter ein und initialisiert das Benutzer-Theme
 */
function setupThemeToggle() {
    // Finde die Benutzer-Navigation
    const navbarUser = document.querySelector('.navbar-user');
    if (!navbarUser) return;

    // Erstelle den Theme-Umschalter
    const themeButton = document.createElement('button');
    themeButton.className = 'btn btn-sm ml-2 theme-toggle';
    themeButton.innerHTML = '<i class="fas fa-moon"></i>';
    themeButton.title = 'Dunklen Modus umschalten';
    
    // Füge den Button in die Navigation ein (vor dem Profil-Link)
    const profileLink = navbarUser.querySelector('a[href*="profile.php"]');
    if (profileLink) {
        navbarUser.insertBefore(themeButton, profileLink);
    } else {
        navbarUser.appendChild(themeButton);
    }
    
    // Lade die aktuelle Theme-Einstellung
    const currentTheme = localStorage.getItem('dojaTheme') || 'light';
    applyTheme(currentTheme);
    
    // Event-Listener für den Theme-Umschalter
    themeButton.addEventListener('click', function() {
        const currentTheme = document.body.classList.contains('dark-theme') ? 'light' : 'dark';
        applyTheme(currentTheme);
        localStorage.setItem('dojaTheme', currentTheme);
    });
}

/**
 * Wendet das ausgewählte Theme auf die Seite an
 * @param {string} theme - 'light' oder 'dark'
 */
function applyTheme(theme) {
    const toggleButton = document.querySelector('.theme-toggle');
    
    if (theme === 'dark') {
        document.body.classList.add('dark-theme');
        if (toggleButton) {
            toggleButton.innerHTML = '<i class="fas fa-sun"></i>';
            toggleButton.title = 'Hellen Modus umschalten';
        }
    } else {
        document.body.classList.remove('dark-theme');
        if (toggleButton) {
            toggleButton.innerHTML = '<i class="fas fa-moon"></i>';
            toggleButton.title = 'Dunklen Modus umschalten';
        }
    }
    
    // Theme-Event für andere Komponenten auslösen
    document.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
}

/**
 * Verbessert die Ausklapp-Funktionalität für alle Akkordeon-Elemente im Dokument
 */
function enhanceAccordions() {
    const accordionButtons = document.querySelectorAll('.accordion-toggle');
    
    accordionButtons.forEach(button => {
        ensureButtonAttributes(button);
        button.addEventListener('click', toggleAccordion);
    });
}

/**
 * Event-Handler für das Umschalten der Akkordeon-Abschnitte
 * @param {Event} event - Das Klick-Event
 */
function toggleAccordion(event) {
    event.preventDefault();
    
    const targetId = this.getAttribute('data-target');
    const targetContent = document.querySelector(targetId);
    
    if (!targetContent) return;
    
    // Toggle aria-expanded
    const isExpanded = this.getAttribute('aria-expanded') === 'true';
    this.setAttribute('aria-expanded', !isExpanded);
    
    // Toggle collapse class
    targetContent.classList.toggle('show');
    
    // Ändere das Icon
    const icon = this.querySelector('i.fas');
    if (icon) {
        if (targetContent.classList.contains('show')) {
            icon.classList.remove('fa-plus');
            icon.classList.add('fa-minus');
        } else {
            icon.classList.remove('fa-minus'); 
            icon.classList.add('fa-plus');
        }
    }
}

/**
 * Stellt sicher, dass ein Akkordeon-Button alle notwendigen Attribute hat
 * @param {HTMLElement} button - Der zu prüfende Button
 */
function ensureButtonAttributes(button) {
    if (!button.hasAttribute('data-target')) {
        const nextEl = button.nextElementSibling;
        if (nextEl && nextEl.classList.contains('collapse')) {
            const targetId = nextEl.id || 'collapse-' + Math.random().toString(36).substr(2, 9);
            nextEl.id = targetId;
            button.setAttribute('data-target', '#' + targetId);
        }
    }
    
    if (!button.hasAttribute('aria-expanded')) {
        const targetId = button.getAttribute('data-target');
        const targetEl = targetId ? document.querySelector(targetId) : null;
        const isExpanded = targetEl ? targetEl.classList.contains('show') : false;
        button.setAttribute('aria-expanded', isExpanded);
    }
    
    if (!button.hasAttribute('aria-controls')) {
        const targetId = button.getAttribute('data-target');
        if (targetId) {
            button.setAttribute('aria-controls', targetId.substring(1));
        }
    }
}