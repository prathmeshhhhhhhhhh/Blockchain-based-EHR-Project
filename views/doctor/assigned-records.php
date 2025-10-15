<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/config.php';

requireRole('DOCTOR');

$user = getCurrentUser();
$title = 'Assigned Records';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clipboard-check"></i> Assigned Records</h2>
    <span class="badge bg-success fs-6">DOCTOR</span>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Records Assigned to Me</h5>
            </div>
            <div class="card-body">
                <div id="assignedRecordsContainer">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-12">
                        <h4 class="text-primary" id="totalAssigned">0</h4>
                        <p class="text-muted">Total Assigned</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Navigation</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= route('doctor/patients') ?>" class="btn btn-outline-primary">
                        <i class="bi bi-people"></i> My Patients
                    </a>
                    <a href="<?= route('doctor/search') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-search"></i> Search Patients
                    </a>
                    <a href="<?= route('dashboard') ?>" class="btn btn-outline-info">
                        <i class="bi bi-house"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Record Modal -->
<div class="modal fade" id="viewRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Medical Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewRecordContent">
                <!-- Record content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAssignedRecords();
});

function loadAssignedRecords() {
    fetch('<?= route('doctor/assigned-records-api') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('assignedRecordsContainer').innerHTML = 
                    '<div class="alert alert-danger">' + data.error + '</div>';
                return;
            }
            
            displayAssignedRecords(data.assigned_records || []);
            updateStats(data.assigned_records || []);
        })
        .catch(error => {
            document.getElementById('assignedRecordsContainer').innerHTML = 
                '<div class="alert alert-danger">Error loading assigned records</div>';
            console.error('Error loading assigned records:', error);
        });
}

function displayAssignedRecords(records) {
    const container = document.getElementById('assignedRecordsContainer');
    
    if (records.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-clipboard-x display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Assigned Records</h4>
                <p class="text-muted">You don't have any records assigned to you yet.</p>
                <a href="<?= route('doctor/patients') ?>" class="btn btn-primary">
                    <i class="bi bi-people"></i> View My Patients
                </a>
            </div>
        `;
        return;
    }
    
    const recordsHtml = records.map(record => `
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title">
                            <span class="badge bg-${getTypeColor(record.type)}">${record.type}</span>
                            ${formatDateTime(record.recorded_at)}
                        </h5>
                        <p class="card-text">${getRecordSummary(record)}</p>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Patient:</strong> ${record.patient_name}<br>
                                    <strong>Email:</strong> ${record.patient_email}
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Assigned:</strong> ${formatDateTime(record.assigned_at)}<br>
                                    <strong>Created by:</strong> ${record.created_by_name || 'Unknown'}
                                </small>
                            </div>
                        </div>
                        ${record.assignment_note ? `
                            <div class="mt-2">
                                <small class="text-info">
                                    <strong>Assignment Note:</strong> ${record.assignment_note}
                                </small>
                            </div>
                        ` : ''}
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewRecord(${record.record_id})">
                            <i class="bi bi-eye"></i> View Details
                        </button>
                        <a href="<?= route('doctor/patient-records') ?>&patientId=${record.patient_id}" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-person"></i> View Patient
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = recordsHtml;
}

function updateStats(records) {
    document.getElementById('totalAssigned').textContent = records.length;
}

function getTypeColor(type) {
    const colors = {
        'ENCOUNTER': 'primary',
        'LAB': 'success',
        'PRESCRIPTION': 'info',
        'NOTE': 'secondary',
        'VITAL': 'warning',
        'ALLERGY': 'danger',
        'IMAGING': 'dark'
    };
    return colors[type] || 'secondary';
}

function getRecordSummary(record) {
    const content = record.content;
    switch (record.type) {
        case 'ENCOUNTER':
            return content.chief_complaint || 'Encounter record';
        case 'LAB':
            return `${content.test_name || 'Lab test'}: ${content.result || 'No result'}`;
        case 'PRESCRIPTION':
            return `${content.medication || 'Medication'}: ${content.dosage || 'No dosage'}`;
        case 'NOTE':
            return content.note || 'Note record';
        case 'VITAL':
            return `${content.vital_type || 'Vital'}: ${content.value || 'No value'}`;
        case 'ALLERGY':
            return `${content.allergen || 'Allergen'}: ${content.severity || 'Unknown severity'}`;
        case 'IMAGING':
            return `${content.study_type || 'Imaging study'}: ${content.findings || 'No findings'}`;
        default:
            return 'Medical record';
    }
}

function formatDateTime(dateTime) {
    return new Date(dateTime).toLocaleString();
}

function viewRecord(recordId) {
    fetch(`<?= route('ehr/get') ?>&id=${recordId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
                return;
            }
            
            const record = data.record;
            const content = record.content;
            
            let contentHtml = '';
            
            switch (record.type) {
                case 'ENCOUNTER':
                    contentHtml = `
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Chief Complaint:</strong><br>
                                <p>${content.chief_complaint || 'Not specified'}</p>
                            </div>
                            <div class="col-md-6">
                                <strong>Diagnosis:</strong><br>
                                <p>${content.diagnosis || 'Not specified'}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <strong>Treatment:</strong><br>
                                <p>${content.treatment || 'Not specified'}</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'LAB':
                    contentHtml = `
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Test Name:</strong><br>
                                <p>${content.test_name || 'Not specified'}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>Result:</strong><br>
                                <p>${content.result || 'Not specified'}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>Reference Range:</strong><br>
                                <p>${content.reference_range || 'Not specified'}</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'PRESCRIPTION':
                    contentHtml = `
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Medication:</strong><br>
                                <p>${content.medication || 'Not specified'}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>Dosage:</strong><br>
                                <p>${content.dosage || 'Not specified'}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>Instructions:</strong><br>
                                <p>${content.instructions || 'Not specified'}</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'NOTE':
                    contentHtml = `
                        <div class="row">
                            <div class="col-12">
                                <strong>Note:</strong><br>
                                <p>${content.note || 'No note content'}</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'VITAL':
                    contentHtml = `
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Vital Type:</strong><br>
                                <p>${content.vital_type || 'Not specified'}</p>
                            </div>
                            <div class="col-md-6">
                                <strong>Value:</strong><br>
                                <p>${content.value || 'Not specified'}</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'ALLERGY':
                    contentHtml = `
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Allergen:</strong><br>
                                <p>${content.allergen || 'Not specified'}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>Severity:</strong><br>
                                <p>${content.severity || 'Not specified'}</p>
                            </div>
                            <div class="col-md-4">
                                <strong>Reaction:</strong><br>
                                <p>${content.reaction || 'Not specified'}</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'IMAGING':
                    contentHtml = `
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Study Type:</strong><br>
                                <p>${content.study_type || 'Not specified'}</p>
                            </div>
                            <div class="col-md-6">
                                <strong>Findings:</strong><br>
                                <p>${content.findings || 'Not specified'}</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <strong>Impression:</strong><br>
                                <p>${content.impression || 'Not specified'}</p>
                            </div>
                        </div>
                    `;
                    break;
                default:
                    contentHtml = '<p>Record content not available</p>';
            }
            
            document.getElementById('viewRecordContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>Record Type:</strong> <span class="badge bg-${getTypeColor(record.type)}">${record.type}</span><br>
                        <strong>Recorded At:</strong> ${formatDateTime(record.recorded_at)}<br>
                        <strong>Created By:</strong> ${record.created_by_name || 'Unknown'}
                    </div>
                    <div class="col-md-6">
                        <strong>Created At:</strong> ${formatDateTime(record.created_at)}<br>
                        <strong>Content Hash:</strong> <code>${record.content_hash.substring(0, 16)}...</code>
                    </div>
                </div>
                <hr>
                <h6>Record Content:</h6>
                ${contentHtml}
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('viewRecordModal'));
            modal.show();
        })
        .catch(error => {
            showAlert('Error loading record details', 'danger');
        });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
