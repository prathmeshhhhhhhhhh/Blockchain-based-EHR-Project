// MediHub Application JavaScript

// Global functions
function showAlert(message, type = 'info') {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} alert-dismissible fade show`;
    alertContainer.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container');
    container.insertBefore(alertContainer, container.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertContainer.parentNode) {
            alertContainer.remove();
        }
    }, 5000);
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch(apiUrl('auth/logout'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            window.location.href = (window.BASE_URL || '/');
        })
        .catch(error => {
            console.error('Logout error:', error);
            window.location.href = (window.BASE_URL || '/');
        });
    }
}

// API helper functions
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(endpoint, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'API call failed');
        }
        
        return result;
    } catch (error) {
        console.error('API call error:', error);
        showAlert(error.message, 'danger');
        throw error;
    }
}

// Modal helper functions
function showModal(title, content, size = '') {
    const modalHtml = `
        <div class="modal fade" id="dynamicModal" tabindex="-1">
            <div class="modal-dialog ${size}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${title}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${content}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal
    const existingModal = document.getElementById('dynamicModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add new modal
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('dynamicModal'));
    modal.show();
    
    // Clean up when hidden
    document.getElementById('dynamicModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Form validation helpers
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePassword(password) {
    return password.length >= 8;
}

// Date formatting helpers
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString();
}

// File upload helpers
function validateFile(file, maxSize = 5 * 1024 * 1024, allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif']) {
    if (file.size > maxSize) {
        throw new Error('File too large. Maximum size is 5MB.');
    }
    
    if (!allowedTypes.includes(file.type)) {
        throw new Error('Invalid file type. Allowed types: PDF, JPG, PNG, GIF');
    }
    
    return true;
}

// Table helpers
function createDataTable(containerId, data, columns, actions = []) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    if (!data || data.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No data available</p>';
        return;
    }
    
    let tableHtml = `
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark" style="background-color: #212529 !important; color: #ffffff !important;">
                    <tr>
                        ${columns.map(col => `<th style="background-color: #212529 !important; color: #ffffff !important; border-color: #495057 !important; font-weight: 600 !important;">${col.title}</th>`).join('')}
                        ${actions.length > 0 ? '<th style="background-color: #212529 !important; color: #ffffff !important; border-color: #495057 !important; font-weight: 600 !important;">Actions</th>' : ''}
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        tableHtml += '<tr>';
        columns.forEach(col => {
            const value = col.render ? col.render(row[col.key], row) : row[col.key];
            tableHtml += `<td>${value}</td>`;
        });
        
        if (actions.length > 0) {
            tableHtml += '<td>';
            actions.forEach(action => {
                const isVisible = action.visible ? action.visible(row) : true;
                if (isVisible) {
                    tableHtml += `<button class="btn btn-sm ${action.class}" onclick="${action.onclick}(this, ${JSON.stringify(row).replace(/"/g, '&quot;')})">${action.text}</button> `;
                }
            });
            tableHtml += '</td>';
        }
        
        tableHtml += '</tr>';
    });
    
    tableHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = tableHtml;
}

// Notification helpers
function showNotification(title, message, type = 'info') {
    // This would integrate with a notification system
    showAlert(`${title}: ${message}`, type);
}

// Consent management helpers
function createConsentForm(patientId, doctorId) {
    const formHtml = `
        <form id="consentForm">
            <input type="hidden" name="patientId" value="${patientId}">
            <input type="hidden" name="doctorId" value="${doctorId}">
            
            <div class="mb-3">
                <label for="purpose" class="form-label">Purpose *</label>
                <select class="form-select" name="purpose" required>
                    <option value="">Select purpose</option>
                    <option value="TREATMENT">Treatment</option>
                    <option value="RESEARCH">Research</option>
                    <option value="EMERGENCY">Emergency</option>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="startAt" class="form-label">Start Date & Time *</label>
                        <input type="datetime-local" class="form-control" name="startAt" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="endAt" class="form-label">End Date & Time *</label>
                        <input type="datetime-local" class="form-control" name="endAt" required>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="maxViews" class="form-label">Maximum Views (optional)</label>
                <input type="number" class="form-control" name="maxViews" min="1">
                <div class="form-text">Leave empty for unlimited views</div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Data Scopes *</label>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="DEMOGRAPHICS" id="scope_demographics">
                            <label class="form-check-label" for="scope_demographics">Demographics</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="ENCOUNTERS" id="scope_encounters">
                            <label class="form-check-label" for="scope_encounters">Encounters</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="LABS" id="scope_labs">
                            <label class="form-check-label" for="scope_labs">Lab Results</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="PRESCRIPTIONS" id="scope_prescriptions">
                            <label class="form-check-label" for="scope_prescriptions">Prescriptions</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="NOTES" id="scope_notes">
                            <label class="form-check-label" for="scope_notes">Notes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="DOCUMENTS" id="scope_documents">
                            <label class="form-check-label" for="scope_documents">Documents</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Create Consent</button>
            </div>
        </form>
    `;
    
    return formHtml;
}

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    // Set default datetime values for consent forms
    const now = new Date();
    const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    
    const startInputs = document.querySelectorAll('input[name="startAt"]');
    const endInputs = document.querySelectorAll('input[name="endAt"]');
    
    startInputs.forEach(input => {
        if (!input.value) {
            input.value = now.toISOString().slice(0, 16);
        }
    });
    
    endInputs.forEach(input => {
        if (!input.value) {
            input.value = tomorrow.toISOString().slice(0, 16);
        }
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    });
});

// Export functions for global use
window.MediHub = {
    showAlert,
    logout,
    apiCall,
    showModal,
    validateEmail,
    validatePassword,
    formatDate,
    formatDateTime,
    validateFile,
    createDataTable,
    showNotification,
    createConsentForm
};
