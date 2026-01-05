/**
 * Permission Utility Functions
 * Handles permission-related UI interactions
 */

/**
 * Show permission denial popup
 */
function showPermissionDenied() {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.role = 'alert';
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '350px';
    alertDiv.innerHTML = `
        <strong>Zugriff verweigert!</strong><br>
        Du hast keine Berechtigung dies zu tun.
        <button type="button" class="btn-close" data-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Initialize Bootstrap alert dismiss
    if (typeof $ !== 'undefined') {
        $(alertDiv).find('.btn-close').on('click', function() {
            $(alertDiv).remove();
        });
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

/**
 * Show modal-based permission denial
 */
function showPermissionDeniedModal() {
    // Use Bootstrap 4 compatible modal approach
    const modalHtml = `
        <div class="modal fade" id="permissionDeniedModal" tabindex="-1" role="dialog" aria-labelledby="permissionDeniedModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="permissionDeniedModalLabel">Zugriff verweigert</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p><strong>Du hast keine Berechtigung dies zu tun.</strong></p>
                        <p>Kontaktiere einen Administrator, wenn du diese Berechtigung benötigst.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if present
    const existingModal = document.getElementById('permissionDeniedModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create and show modal using jQuery/Bootstrap 4
    if (typeof $ !== 'undefined') {
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        $('#permissionDeniedModal').modal('show');
    }
}

/**
 * Check if edit action is allowed via button click
 */
function checkEditPermission(event) {
    // Check for data-requires-permission attribute
    const button = event.currentTarget;
    if (button.hasAttribute('data-requires-permission') && 
        button.getAttribute('data-requires-permission') === 'edit') {
        
        // Check if button has disabled state or permission-denied class
        if (button.hasAttribute('disabled') || button.classList.contains('permission-denied')) {
            event.preventDefault();
            event.stopPropagation();
            showPermissionDenied();
            return false;
        }
    }
    return true;
}

/**
 * Check if delete action is allowed via button click
 */
function checkDeletePermission(event) {
    const button = event.currentTarget;
    if (button.hasAttribute('data-requires-permission') && 
        button.getAttribute('data-requires-permission') === 'delete') {
        
        if (button.hasAttribute('disabled') || button.classList.contains('permission-denied')) {
            event.preventDefault();
            event.stopPropagation();
            showPermissionDeniedModal();
            return false;
        }
    }
    return true;
}

/**
 * Disable edit buttons if no permission
 * Uses event delegation to avoid adding multiple listeners
 */
function disableEditButtons() {
    const editButtons = document.querySelectorAll('button[data-requires-permission="edit"]');
    
    editButtons.forEach(btn => {
        if (btn.hasAttribute('data-has-permission') && 
            btn.getAttribute('data-has-permission') === 'false') {
            btn.disabled = true;
            btn.classList.add('permission-denied');
            btn.setAttribute('title', 'Du hast keine Berechtigung zum Bearbeiten');
            
            // Remove any existing listeners first (prevents duplicate listeners)
            btn.removeEventListener('click', checkEditPermission);
            // Add listener once
            btn.addEventListener('click', checkEditPermission, { once: false });
        }
    });
}

/**
 * Disable delete buttons if no permission
 * Uses event delegation to avoid adding multiple listeners
 */
function disableDeleteButtons() {
    const deleteButtons = document.querySelectorAll('button[data-requires-permission="delete"]');
    
    deleteButtons.forEach(btn => {
        if (btn.hasAttribute('data-has-permission') && 
            btn.getAttribute('data-has-permission') === 'false') {
            btn.disabled = true;
            btn.classList.add('permission-denied');
            btn.setAttribute('title', 'Du hast keine Berechtigung zum Löschen');
            
            // Remove any existing listeners first (prevents duplicate listeners)
            btn.removeEventListener('click', checkDeletePermission);
            // Add listener once
            btn.addEventListener('click', checkDeletePermission, { once: false });
        }
    });
}

/**
 * Initialize permission checks on page load
 * Add a small delay to ensure DOM is ready
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            disableEditButtons();
            disableDeleteButtons();
        }, 100);
    });
} else {
    // DOM is already ready
    setTimeout(() => {
        disableEditButtons();
        disableDeleteButtons();
    }, 100);
}

