<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('PATIENT');

$user = getCurrentUser();
$title = 'Consent Management';

// Get patient ID
$stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    header('Location: ' . BASE_URL . '/?r=dashboard');
    exit;
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Consent Management</h2>
    <button class="btn btn-primary" onclick="showCreateConsentModal()">
        <i class="bi bi-plus-circle"></i> Create Consent
    </button>
</div>

<div id="consentsContainer">
    <div class="text-center">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>

<!-- Create Consent Modal -->
<div class="modal fade" id="createConsentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Consent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createConsentForm">
                    <input type="hidden" name="patientId" value="<?= $patient['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="doctorSelect" class="form-label">Doctor *</label>
                        <select class="form-select" name="doctorId" id="doctorSelect" required>
                            <option value="">Select a doctor</option>
                        </select>
                        <div class="form-text" id="doctorSelectHelp"></div>
                    </div>
                    
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
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default datetime values
    const now = new Date();
    const tomorrow = new Date(now.getTime() + 24 * 60 * 60 * 1000);
    
    document.querySelector('input[name="startAt"]').value = now.toISOString().slice(0, 16);
    document.querySelector('input[name="endAt"]').value = tomorrow.toISOString().slice(0, 16);
    
    loadConsents();
    loadApprovedDoctors();
});

function loadConsents() {
    const patientId = <?= $patient['id'] ?>;
    
    fetch(`<?= route('consents/list') ?>&patientId=${patientId}`)
        .then(response => response.json())
        .then(data => {
            displayConsents(data.consents || []);
        })
        .catch(error => {
            document.getElementById('consentsContainer').innerHTML = 
                '<div class="alert alert-danger">Error loading consents</div>';
        });
}

function displayConsents(consents) {
    const container = document.getElementById('consentsContainer');
    
    if (consents.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No consents found</div>';
        return;
    }
    
    const consentsHtml = consents.map(consent => `
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title">
                            <span class="badge bg-${getStatusColor(consent.status)}">${consent.status}</span>
                            ${consent.doctor_name}
                        </h5>
                        <p class="card-text">
                            <strong>Purpose:</strong> ${consent.purpose}<br>
                            <strong>Period:</strong> ${formatDate(consent.start_at)} - ${formatDate(consent.end_at)}<br>
                            <strong>Scopes:</strong> ${consent.scopes.map(scope => `<span class="badge bg-secondary me-1">${scope}</span>`).join('')}<br>
                            <strong>Views:</strong> ${consent.used_views || 0}${consent.max_views ? ` / ${consent.max_views}` : ' / Unlimited'}
                        </p>
                        <small class="text-muted">
                            Created: ${formatDateTime(consent.created_at)}
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        ${consent.status === 'ACTIVE' ? `
                            <button class="btn btn-sm btn-danger" onclick="revokeConsent(${consent.id})">
                                <i class="bi bi-x-circle"></i> Revoke
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = consentsHtml;
}

function getStatusColor(status) {
    const colors = {
        'ACTIVE': 'success',
        'REVOKED': 'danger',
        'EXPIRED': 'secondary'
    };
    return colors[status] || 'secondary';
}

function loadApprovedDoctors() {
    const patientId = <?= $patient['id'] ?>;
    
    fetch(`<?= route('links/list') ?>&by=patient`)
        .then(response => response.json())
        .then(data => {
            const approvedDoctors = data.links
                .filter(link => link.status === 'APPROVED')
                .map(link => ({
                    id: link.doctor_id,
                    name: link.doctor_name,
                    organization: link.organization
                }));
            
            const select = document.getElementById('doctorSelect');
            select.innerHTML = '<option value="">Select a doctor</option>' +
                approvedDoctors.map(doctor => 
                    `<option value="${doctor.id}">${doctor.name} (${doctor.organization})</option>`
                ).join('');

            const help = document.getElementById('doctorSelectHelp');
            if (approvedDoctors.length === 0) {
                help.innerHTML = `No approved doctors yet. Go to <a href="<?= route('patient/requests') ?>">Doctor Requests</a> to approve a doctor's access, then return here to create consent.`;
            } else {
                help.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Error loading doctors:', error);
        });
}

function showCreateConsentModal() {
    const modal = new bootstrap.Modal(document.getElementById('createConsentModal'));
    modal.show();
}

function revokeConsent(consentId) {
    if (confirm('Are you sure you want to revoke this consent?')) {
        fetch('<?= route('consents/revoke') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({consentId: consentId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
            } else {
                showAlert('Consent revoked successfully', 'success');
                loadConsents();
            }
        })
        .catch(error => {
            showAlert('Error revoking consent', 'danger');
        });
    }
}

// Handle form submission
document.getElementById('createConsentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    // Convert scopes array
    data.scopes = Array.from(document.querySelectorAll('input[name="scopes[]"]:checked')).map(cb => cb.value);
    
    if (data.scopes.length === 0) {
        showAlert('Please select at least one scope', 'danger');
        return;
    }
    
    fetch('<?= route('consents/create') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showAlert(data.error, 'danger');
        } else {
            showAlert('Consent created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createConsentModal')).hide();
            this.reset();
            loadConsents();
        }
    })
    .catch(error => {
        showAlert('Error creating consent', 'danger');
    });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
