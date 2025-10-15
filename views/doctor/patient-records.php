<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/config.php';

requireRole('DOCTOR');

$user = getCurrentUser();
$patientId = (int)($_GET['patientId'] ?? 0);

if (!$patientId) {
    header('Location: ' . route('doctor/patients'));
    exit;
}

// Get patient information
global $pdo;
try {
    $stmt = $pdo->prepare("SELECT p.id, u.full_name, u.email 
                          FROM patients p 
                          JOIN users u ON p.user_id = u.id 
                          WHERE p.id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    
    if (!$patient) {
        header('Location: ' . route('doctor/patients'));
        exit;
    }
    
    // Check if doctor has access to this patient
    $stmt = $pdo->prepare("SELECT l.id FROM links l 
                          JOIN doctors d ON l.doctor_id = d.id 
                          WHERE l.patient_id = ? AND d.user_id = ? AND l.status = 'APPROVED'");
    $stmt->execute([$patientId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        header('Location: ' . route('doctor/patients'));
        exit;
    }
    
} catch (Exception $e) {
    header('Location: ' . route('doctor/patients'));
    exit;
}

$title = 'Patient Records - ' . $patient['full_name'];

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="bi bi-file-medical"></i> Patient Records</h2>
        <p class="text-muted mb-0">Patient: <strong><?= htmlspecialchars($patient['full_name']) ?></strong> (<?= htmlspecialchars($patient['email']) ?>)</p>
    </div>
    <div>
        <span class="badge bg-success fs-6">DOCTOR</span>
        <a href="<?= route('doctor/patients') ?>" class="btn btn-outline-secondary ms-2">
            <i class="bi bi-arrow-left"></i> Back to Patients
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Medical Records</h5>
            </div>
            <div class="card-body">
                <div id="recordsContainer">
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
                <h5 class="mb-0">Patient Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <p><strong>Name:</strong> <?= htmlspecialchars($patient['full_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></p>
                        <p><strong>Patient ID:</strong> <?= $patient['id'] ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="addRecord()">
                        <i class="bi bi-plus"></i> Add Record
                    </button>
                    <a href="<?= route('doctor/patients') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-people"></i> All Patients
                    </a>
                    <a href="<?= route('doctor/search') ?>" class="btn btn-outline-info">
                        <i class="bi bi-search"></i> Search Patients
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
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveRecord()">Add Record</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadRecords();
    
    // Set default datetime to now
    document.getElementById('recordedAt').value = new Date().toISOString().slice(0, 16);
});

function loadRecords() {
    fetch(`<?= route('doctor/patient-records-api') ?>&patientId=<?= $patient['id'] ?>`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                document.getElementById('recordsContainer').innerHTML = 
                    '<div class="alert alert-danger">' + data.error + '</div>';
                return;
            }
            
            displayRecords(data.records || []);
        })
        .catch(error => {
            document.getElementById('recordsContainer').innerHTML = 
                '<div class="alert alert-danger">Error loading records</div>';
            console.error('Error loading records:', error);
        });
}

function displayRecords(records) {
    const container = document.getElementById('recordsContainer');
    
    if (records.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-file-medical display-1 text-muted"></i>
                <h4 class="text-muted mt-3">No Records Found</h4>
                <p class="text-muted">This patient doesn't have any medical records yet.</p>
                <button class="btn btn-primary" onclick="addRecord()">
                    <i class="bi bi-plus"></i> Add First Record
                </button>
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
                        <small class="text-muted">
                            Created by: ${record.created_by_name || 'Unknown'} | 
                            Hash: ${record.content_hash.substring(0, 16)}...
                        </small>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewRecord(${record.id})">
                            <i class="bi bi-eye"></i> View
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

function addRecord() {
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
                    <label for="chiefComplaint" class="form-label">Chief Complaint</label>
                    <input type="text" class="form-control" name="chief_complaint" id="chiefComplaint">
                </div>
                <div class="mb-3">
                    <label for="diagnosis" class="form-label">Diagnosis</label>
                    <input type="text" class="form-control" name="diagnosis" id="diagnosis">
                </div>
                <div class="mb-3">
                    <label for="treatment" class="form-label">Treatment</label>
                    <textarea class="form-control" name="treatment" id="treatment" rows="3"></textarea>
                </div>
            `;
            break;
        case 'LAB':
            formHtml = `
                <div class="mb-3">
                    <label for="testName" class="form-label">Test Name</label>
                    <input type="text" class="form-control" name="test_name" id="testName">
                </div>
                <div class="mb-3">
                    <label for="result" class="form-label">Result</label>
                    <input type="text" class="form-control" name="result" id="result">
                </div>
                <div class="mb-3">
                    <label for="referenceRange" class="form-label">Reference Range</label>
                    <input type="text" class="form-control" name="reference_range" id="referenceRange">
                </div>
            `;
            break;
        case 'PRESCRIPTION':
            formHtml = `
                <div class="mb-3">
                    <label for="medication" class="form-label">Medication</label>
                    <input type="text" class="form-control" name="medication" id="medication">
                </div>
                <div class="mb-3">
                    <label for="dosage" class="form-label">Dosage</label>
                    <input type="text" class="form-control" name="dosage" id="dosage">
                </div>
                <div class="mb-3">
                    <label for="instructions" class="form-label">Instructions</label>
                    <textarea class="form-control" name="instructions" id="instructions" rows="3"></textarea>
                </div>
            `;
            break;
        case 'NOTE':
            formHtml = `
                <div class="mb-3">
                    <label for="note" class="form-label">Note</label>
                    <textarea class="form-control" name="note" id="note" rows="5"></textarea>
                </div>
            `;
            break;
        case 'VITAL':
            formHtml = `
                <div class="mb-3">
                    <label for="vitalType" class="form-label">Vital Type</label>
                    <select class="form-select" name="vital_type" id="vitalType">
                        <option value="">Select vital type</option>
                        <option value="BP">Blood Pressure</option>
                        <option value="HR">Heart Rate</option>
                        <option value="TEMP">Temperature</option>
                        <option value="RR">Respiratory Rate</option>
                        <option value="O2">Oxygen Saturation</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="value" class="form-label">Value</label>
                    <input type="text" class="form-control" name="value" id="value">
                </div>
            `;
            break;
        case 'ALLERGY':
            formHtml = `
                <div class="mb-3">
                    <label for="allergen" class="form-label">Allergen</label>
                    <input type="text" class="form-control" name="allergen" id="allergen">
                </div>
                <div class="mb-3">
                    <label for="severity" class="form-label">Severity</label>
                    <select class="form-select" name="severity" id="severity">
                        <option value="">Select severity</option>
                        <option value="MILD">Mild</option>
                        <option value="MODERATE">Moderate</option>
                        <option value="SEVERE">Severe</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="reaction" class="form-label">Reaction</label>
                    <textarea class="form-control" name="reaction" id="reaction" rows="3"></textarea>
                </div>
            `;
            break;
        case 'IMAGING':
            formHtml = `
                <div class="mb-3">
                    <label for="studyType" class="form-label">Study Type</label>
                    <input type="text" class="form-control" name="study_type" id="studyType">
                </div>
                <div class="mb-3">
                    <label for="findings" class="form-label">Findings</label>
                    <textarea class="form-control" name="findings" id="findings" rows="4"></textarea>
                </div>
                <div class="mb-3">
                    <label for="impression" class="form-label">Impression</label>
                    <textarea class="form-control" name="impression" id="impression" rows="3"></textarea>
                </div>
            `;
            break;
    }
    
    contentDiv.innerHTML = formHtml;
}

function saveRecord() {
    const form = document.getElementById('addRecordForm');
    const formData = new FormData(form);
    
    // Build content object based on record type
    const type = formData.get('type');
    const content = {};
    
    // Get all form fields and build content object
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        if (input.name && !['patientId', 'type', 'recordedAt'].includes(input.name)) {
            content[input.name] = input.value;
        }
    });
    
    const data = {
        patientId: formData.get('patientId'),
        type: type,
        recordedAt: formData.get('recordedAt'),
        content: content
    };
    
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
            form.reset();
            loadRecords();
        }
    })
    .catch(error => {
        showAlert('Error creating record', 'danger');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
