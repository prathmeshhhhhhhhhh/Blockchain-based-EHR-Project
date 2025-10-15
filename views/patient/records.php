<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('PATIENT');

$user = getCurrentUser();
$title = 'My Medical Records';

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
    <h2>My Medical Records</h2>
    <button class="btn btn-primary" onclick="showAddRecordModal()">
        <i class="bi bi-plus-circle"></i> Add Record
    </button>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <select class="form-select" id="typeFilter" onchange="filterRecords()">
            <option value="">All Types</option>
            <option value="ENCOUNTER">Encounters</option>
            <option value="LAB">Lab Results</option>
            <option value="PRESCRIPTION">Prescriptions</option>
            <option value="NOTE">Notes</option>
            <option value="VITAL">Vitals</option>
            <option value="ALLERGY">Allergies</option>
            <option value="IMAGING">Imaging</option>
        </select>
    </div>
    <div class="col-md-4">
        <input type="date" class="form-control" id="dateFrom" placeholder="From Date" onchange="filterRecords()">
    </div>
    <div class="col-md-4">
        <input type="date" class="form-control" id="dateTo" placeholder="To Date" onchange="filterRecords()">
    </div>
</div>

<div id="recordsContainer">
    <div class="text-center">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>

<!-- Add Record Modal -->
<div class="modal fade" id="addRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addRecordForm">
                    <input type="hidden" name="patientId" value="<?= $patient['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="recordType" class="form-label">Record Type *</label>
                        <select class="form-select" name="type" id="recordType" required onchange="updateRecordForm()">
                            <option value="">Select type</option>
                            <option value="ENCOUNTER">Encounter</option>
                            <option value="LAB">Lab Result</option>
                            <option value="PRESCRIPTION">Prescription</option>
                            <option value="NOTE">Note</option>
                            <option value="VITAL">Vital Sign</option>
                            <option value="ALLERGY">Allergy</option>
                            <option value="IMAGING">Imaging</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="recordedAt" class="form-label">Recorded Date & Time *</label>
                        <input type="datetime-local" class="form-control" name="recordedAt" id="recordedAt" required>
                    </div>
                    
                    <div id="recordContent">
                        <!-- Dynamic content based on record type -->
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Add Record</button>
                    </div>
                </form>
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
                <button type="submit" class="btn btn-primary">Add Record</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Record Modal -->
<div class="modal fade" id="editRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Medical Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editRecordForm">
                    <input type="hidden" name="recordId" id="editRecordId">
                    <input type="hidden" name="patientId" value="<?= $patient['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="editRecordType" class="form-label">Record Type *</label>
                        <select class="form-select" name="type" id="editRecordType" required onchange="updateEditRecordForm()">
                            <option value="">Select type</option>
                            <option value="ENCOUNTER">Encounter</option>
                            <option value="LAB">Lab Result</option>
                            <option value="PRESCRIPTION">Prescription</option>
                            <option value="NOTE">Note</option>
                            <option value="VITAL">Vital Sign</option>
                            <option value="ALLERGY">Allergy</option>
                            <option value="IMAGING">Imaging</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="editRecordedAt" class="form-label">Recorded Date & Time *</label>
                        <input type="datetime-local" class="form-control" name="recordedAt" id="editRecordedAt" required>
                    </div>
                    
                    <div id="editRecordContent">
                        <!-- Dynamic content based on record type -->
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveEditRecord()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Record Modal -->
<div class="modal fade" id="assignRecordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Record to Doctor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignRecordForm">
                    <input type="hidden" name="recordId" id="assignRecordId">
                    
                    <div class="mb-3">
                        <label for="doctorSelect" class="form-label">Select Doctor *</label>
                        <select class="form-select" name="doctorId" id="doctorSelect" required>
                            <option value="">Select a doctor</option>
                            <!-- Will be populated dynamically -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assignmentNote" class="form-label">Assignment Note (Optional)</label>
                        <textarea class="form-control" name="note" id="assignmentNote" rows="3" placeholder="Add a note about this assignment..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveRecordAssignment()">Assign Record</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default datetime to now
    document.getElementById('recordedAt').value = new Date().toISOString().slice(0, 16);
    
    // Load records
    loadRecords();
});

function loadRecords() {
    const patientId = <?= $patient['id'] ?>;
    const type = document.getElementById('typeFilter').value;
    const from = document.getElementById('dateFrom').value;
    const to = document.getElementById('dateTo').value;
    
    let url = `<?= BASE_URL ?>/?r=ehr/list&patientId=${patientId}`;
    if (type) url += `&type=${type}`;
    if (from) url += `&from=${from}`;
    if (to) url += `&to=${to}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            displayRecords(data.records || []);
        })
        .catch(error => {
            document.getElementById('recordsContainer').innerHTML = 
                '<div class="alert alert-danger">Error loading records</div>';
        });
}

function displayRecords(records) {
    const container = document.getElementById('recordsContainer');
    
    if (records.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No records found</div>';
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
                        <small class="text-muted">
                            Created by: ${record.created_by_name || 'Unknown'} | 
                            Hash: ${record.content_hash.substring(0, 16)}...
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewRecord(${record.id})">
                            <i class="bi bi-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editRecord(${record.id})">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="assignRecord(${record.id})">
                            <i class="bi bi-person-plus"></i> Assign
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteRecord(${record.id})">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = recordsHtml;
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

function filterRecords() {
    loadRecords();
}

function showAddRecordModal() {
    const modal = new bootstrap.Modal(document.getElementById('addRecordModal'));
    modal.show();
}

function updateRecordForm() {
    const type = document.getElementById('recordType').value;
    const contentDiv = document.getElementById('recordContent');
    
    let formHtml = '';
    
    switch (type) {
        case 'ENCOUNTER':
            formHtml = `
                <div class="mb-3">
                    <label class="form-label">Chief Complaint *</label>
                    <input type="text" class="form-control" name="content[chief_complaint]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Diagnosis *</label>
                    <input type="text" class="form-control" name="content[diagnosis]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Treatment Plan</label>
                    <textarea class="form-control" name="content[treatment_plan]" rows="3"></textarea>
                </div>
            `;
            break;
        case 'LAB':
            formHtml = `
                <div class="mb-3">
                    <label class="form-label">Test Name *</label>
                    <input type="text" class="form-control" name="content[test_name]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Result *</label>
                    <input type="text" class="form-control" name="content[result]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reference Range</label>
                    <input type="text" class="form-control" name="content[reference_range]">
                </div>
            `;
            break;
        case 'PRESCRIPTION':
            formHtml = `
                <div class="mb-3">
                    <label class="form-label">Medication *</label>
                    <input type="text" class="form-control" name="content[medication]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Dosage *</label>
                    <input type="text" class="form-control" name="content[dosage]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Frequency</label>
                    <input type="text" class="form-control" name="content[frequency]">
                </div>
            `;
            break;
        case 'NOTE':
            formHtml = `
                <div class="mb-3">
                    <label class="form-label">Note *</label>
                    <textarea class="form-control" name="content[note]" rows="4" required></textarea>
                </div>
            `;
            break;
        case 'VITAL':
            formHtml = `
                <div class="mb-3">
                    <label class="form-label">Vital Type *</label>
                    <select class="form-select" name="content[vital_type]" required>
                        <option value="">Select type</option>
                        <option value="Blood Pressure">Blood Pressure</option>
                        <option value="Heart Rate">Heart Rate</option>
                        <option value="Temperature">Temperature</option>
                        <option value="Weight">Weight</option>
                        <option value="Height">Height</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Value *</label>
                    <input type="text" class="form-control" name="content[value]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Unit</label>
                    <input type="text" class="form-control" name="content[unit]">
                </div>
            `;
            break;
        case 'ALLERGY':
            formHtml = `
                <div class="mb-3">
                    <label class="form-label">Allergen *</label>
                    <input type="text" class="form-control" name="content[allergen]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Severity *</label>
                    <select class="form-select" name="content[severity]" required>
                        <option value="">Select severity</option>
                        <option value="Mild">Mild</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Severe">Severe</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reaction</label>
                    <input type="text" class="form-control" name="content[reaction]">
                </div>
            `;
            break;
        case 'IMAGING':
            formHtml = `
                <div class="mb-3">
                    <label class="form-label">Study Type *</label>
                    <input type="text" class="form-control" name="content[study_type]" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Findings *</label>
                    <textarea class="form-control" name="content[findings]" rows="3" required></textarea>
                </div>
            `;
            break;
    }
    
    contentDiv.innerHTML = formHtml;
}

function viewRecord(recordId) {
    fetch(`<?= route('ehr/get') ?>&id=${recordId}`)
        .then(response => response.json())
        .then(data => {
            if (data.record) {
                displayRecordDetails(data.record);
                const modal = new bootstrap.Modal(document.getElementById('viewRecordModal'));
                modal.show();
            }
        })
        .catch(error => {
            showAlert('Error loading record', 'danger');
        });
}

function displayRecordDetails(record) {
    const content = record.content;
    let detailsHtml = `
        <div class="row">
            <div class="col-md-6">
                <h6>Record Information</h6>
                <p><strong>Type:</strong> ${record.type}</p>
                <p><strong>Recorded:</strong> ${formatDateTime(record.recorded_at)}</p>
                <p><strong>Created by:</strong> ${record.created_by_name || 'Unknown'}</p>
                <p><strong>Content Hash:</strong> <code>${record.content_hash}</code></p>
            </div>
            <div class="col-md-6">
                <h6>Record Content</h6>
                <pre class="bg-light p-3 rounded">${JSON.stringify(content, null, 2)}</pre>
            </div>
        </div>
    `;
    
    if (record.documents && record.documents.length > 0) {
        detailsHtml += `
            <div class="mt-3">
                <h6>Attached Documents</h6>
                <ul class="list-group">
                    ${record.documents.map(doc => `
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${doc.file_name}</span>
                            <div>
                                <span class="badge bg-secondary me-2">${doc.mime_type}</span>
                                <button class="btn btn-sm btn-outline-primary" onclick="downloadDocument(${doc.id})">
                                    <i class="bi bi-download"></i> Download
                                </button>
                            </div>
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;
    }
    
    document.getElementById('viewRecordContent').innerHTML = detailsHtml;
}

function editRecord(recordId) {
    // Implementation for editing records
    showAlert('Edit functionality coming soon', 'info');
}

function deleteRecord(recordId) {
    if (confirm('Are you sure you want to delete this record?')) {
        fetch('<?= route('ehr/delete') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: recordId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
            } else {
                showAlert('Record deleted successfully', 'success');
                loadRecords();
            }
        })
        .catch(error => {
            showAlert('Error deleting record', 'danger');
        });
    }
}

function downloadDocument(documentId) {
    window.open(`<?= BASE_URL ?>/?r=doc/download&id=${documentId}`, '_blank');
}

// Handle form submission
document.getElementById('addRecordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData.entries());
    
    // Convert content object
    const content = {};
    Object.keys(data).forEach(key => {
        if (key.startsWith('content[')) {
            const field = key.match(/content\[(.*?)\]/)[1];
            content[field] = data[key];
        }
    });
    
    data.content = content;
    
    fetch('<?= route('ehr/create') ?>', {
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
            showAlert('Record created successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addRecordModal')).hide();
            this.reset();
            loadRecords();
        }
    })
    .catch(error => {
        showAlert('Error creating record', 'danger');
    });
});

// Edit Record Functions
function editRecord(recordId) {
    // Fetch record details
    fetch(`<?= route('ehr/get') ?>&id=${recordId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
                return;
            }
            
            const record = data.record;
            
            // Populate edit form
            document.getElementById('editRecordId').value = record.id;
            document.getElementById('editRecordType').value = record.type;
            document.getElementById('editRecordedAt').value = record.recorded_at.replace(' ', 'T');
            
            // Update content form based on type
            updateEditRecordForm();
            
            // Populate content fields
            populateEditForm(record);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editRecordModal'));
            modal.show();
        })
        .catch(error => {
            showAlert('Error loading record', 'danger');
        });
}

function updateEditRecordForm() {
    const type = document.getElementById('editRecordType').value;
    const contentDiv = document.getElementById('editRecordContent');
    
    let formHtml = '';
    
    switch (type) {
        case 'ENCOUNTER':
            formHtml = `
                <div class="mb-3">
                    <label for="editChiefComplaint" class="form-label">Chief Complaint</label>
                    <input type="text" class="form-control" name="chief_complaint" id="editChiefComplaint">
                </div>
                <div class="mb-3">
                    <label for="editDiagnosis" class="form-label">Diagnosis</label>
                    <input type="text" class="form-control" name="diagnosis" id="editDiagnosis">
                </div>
                <div class="mb-3">
                    <label for="editTreatment" class="form-label">Treatment</label>
                    <textarea class="form-control" name="treatment" id="editTreatment" rows="3"></textarea>
                </div>
            `;
            break;
        case 'LAB':
            formHtml = `
                <div class="mb-3">
                    <label for="editTestName" class="form-label">Test Name</label>
                    <input type="text" class="form-control" name="test_name" id="editTestName">
                </div>
                <div class="mb-3">
                    <label for="editResult" class="form-label">Result</label>
                    <input type="text" class="form-control" name="result" id="editResult">
                </div>
                <div class="mb-3">
                    <label for="editReferenceRange" class="form-label">Reference Range</label>
                    <input type="text" class="form-control" name="reference_range" id="editReferenceRange">
                </div>
            `;
            break;
        case 'PRESCRIPTION':
            formHtml = `
                <div class="mb-3">
                    <label for="editMedication" class="form-label">Medication</label>
                    <input type="text" class="form-control" name="medication" id="editMedication">
                </div>
                <div class="mb-3">
                    <label for="editDosage" class="form-label">Dosage</label>
                    <input type="text" class="form-control" name="dosage" id="editDosage">
                </div>
                <div class="mb-3">
                    <label for="editInstructions" class="form-label">Instructions</label>
                    <textarea class="form-control" name="instructions" id="editInstructions" rows="3"></textarea>
                </div>
            `;
            break;
        case 'NOTE':
            formHtml = `
                <div class="mb-3">
                    <label for="editNote" class="form-label">Note</label>
                    <textarea class="form-control" name="note" id="editNote" rows="5"></textarea>
                </div>
            `;
            break;
        case 'VITAL':
            formHtml = `
                <div class="mb-3">
                    <label for="editVitalType" class="form-label">Vital Type</label>
                    <select class="form-select" name="vital_type" id="editVitalType">
                        <option value="">Select vital type</option>
                        <option value="BP">Blood Pressure</option>
                        <option value="HR">Heart Rate</option>
                        <option value="TEMP">Temperature</option>
                        <option value="RR">Respiratory Rate</option>
                        <option value="O2">Oxygen Saturation</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editValue" class="form-label">Value</label>
                    <input type="text" class="form-control" name="value" id="editValue">
                </div>
            `;
            break;
        case 'ALLERGY':
            formHtml = `
                <div class="mb-3">
                    <label for="editAllergen" class="form-label">Allergen</label>
                    <input type="text" class="form-control" name="allergen" id="editAllergen">
                </div>
                <div class="mb-3">
                    <label for="editSeverity" class="form-label">Severity</label>
                    <select class="form-select" name="severity" id="editSeverity">
                        <option value="">Select severity</option>
                        <option value="MILD">Mild</option>
                        <option value="MODERATE">Moderate</option>
                        <option value="SEVERE">Severe</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editReaction" class="form-label">Reaction</label>
                    <textarea class="form-control" name="reaction" id="editReaction" rows="3"></textarea>
                </div>
            `;
            break;
        case 'IMAGING':
            formHtml = `
                <div class="mb-3">
                    <label for="editStudyType" class="form-label">Study Type</label>
                    <input type="text" class="form-control" name="study_type" id="editStudyType">
                </div>
                <div class="mb-3">
                    <label for="editFindings" class="form-label">Findings</label>
                    <textarea class="form-control" name="findings" id="editFindings" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <label for="editImpression" class="form-label">Impression</label>
                    <textarea class="form-control" name="impression" id="editImpression" rows="3"></textarea>
                </div>
            `;
            break;
    }
    
    contentDiv.innerHTML = formHtml;
}

function populateEditForm(record) {
    const content = record.content;
    
    // Populate fields based on record type
    Object.keys(content).forEach(key => {
        const field = document.getElementById('edit' + key.charAt(0).toUpperCase() + key.slice(1));
        if (field) {
            field.value = content[key] || '';
        }
    });
}

function saveEditRecord() {
    const form = document.getElementById('editRecordForm');
    const formData = new FormData(form);
    
    // Build content object based on record type
    const type = formData.get('type');
    const content = {};
    
    // Get all form fields and build content object
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        if (input.name && !['recordId', 'patientId', 'type', 'recordedAt'].includes(input.name)) {
            content[input.name] = input.value;
        }
    });
    
    const data = {
        recordId: formData.get('recordId'),
        patientId: formData.get('patientId'),
        type: type,
        recordedAt: formData.get('recordedAt'),
        content: content
    };
    
    fetch('<?= route('ehr/update') ?>', {
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
            showAlert('Record updated successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editRecordModal')).hide();
            loadRecords();
        }
    })
    .catch(error => {
        showAlert('Error updating record', 'danger');
    });
}

// Assign Record Functions
function assignRecord(recordId) {
    document.getElementById('assignRecordId').value = recordId;
    
    // Load available doctors
    loadAvailableDoctors();
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('assignRecordModal'));
    modal.show();
}

function loadAvailableDoctors() {
    fetch('<?= route('links/list') ?>&by=patient')
        .then(response => response.json())
        .then(data => {
            const doctorSelect = document.getElementById('doctorSelect');
            doctorSelect.innerHTML = '<option value="">Select a doctor</option>';
            
            if (data.links && data.links.length > 0) {
                // Get approved doctors
                const approvedDoctors = data.links.filter(link => link.status === 'APPROVED');
                
                approvedDoctors.forEach(link => {
                    const option = document.createElement('option');
                    option.value = link.doctor_id;
                    option.textContent = `${link.doctor_name} (${link.organization || 'No organization'})`;
                    doctorSelect.appendChild(option);
                });
            }
            
            if (doctorSelect.children.length === 1) {
                doctorSelect.innerHTML = '<option value="">No approved doctors available</option>';
            }
        })
        .catch(error => {
            console.error('Error loading doctors:', error);
            document.getElementById('doctorSelect').innerHTML = '<option value="">Error loading doctors</option>';
        });
}

function saveRecordAssignment() {
    const form = document.getElementById('assignRecordForm');
    const formData = new FormData(form);
    
    const data = {
        recordId: formData.get('recordId'),
        doctorId: formData.get('doctorId'),
        note: formData.get('note')
    };
    
    if (!data.doctorId) {
        showAlert('Please select a doctor', 'warning');
        return;
    }
    
    fetch('<?= route('ehr/assign') ?>', {
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
            showAlert('Record assigned successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('assignRecordModal')).hide();
            loadRecords();
        }
    })
    .catch(error => {
        showAlert('Error assigning record', 'danger');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
