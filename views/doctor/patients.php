<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('DOCTOR');

$user = getCurrentUser();
$title = 'My Patients';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Patients</h2>
    <span class="badge bg-success fs-6">DOCTOR</span>
</div>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Search Patient</h5>
            </div>
            <div class="card-body">
                <form id="searchPatientForm">
                    <div class="mb-3">
                        <label for="searchEmail" class="form-label">Patient Email</label>
                        <input type="email" class="form-control" id="searchEmail" name="email" placeholder="Enter patient email">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Search Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <h4 class="text-primary" id="totalPatients">0</h4>
                        <p class="text-muted">Total Patients</p>
                    </div>
                    <div class="col-6">
                        <h4 class="text-success" id="activeConsents">0</h4>
                        <p class="text-muted">Active Consents</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Approved Patients</h5>
    </div>
    <div class="card-body">
        <div id="patientsTable">
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadPatients();
    loadStats();
    
    document.getElementById('searchPatientForm').addEventListener('submit', function(e) {
        e.preventDefault();
        searchPatient();
    });
});

function loadPatients() {
    fetch('<?= route('links/list') ?>&by=doctor')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('patientsTable');
            if (data.links && data.links.length > 0) {
                const approvedPatients = data.links.filter(link => link.status === 'APPROVED');
                
                if (approvedPatients.length > 0) {
                    const columns = [
                        { key: 'patient_name', title: 'Patient Name' },
                        { key: 'patient_email', title: 'Email' },
                        { key: 'status', title: 'Status', render: (value) => `<span class="badge bg-success">${value}</span>` },
                        { key: 'created_at', title: 'Approved', render: (value) => new Date(value).toLocaleDateString() }
                    ];
                    
                    createDataTable('patientsTable', approvedPatients, columns, [
                        {
                            text: 'View Records',
                            class: 'btn btn-sm btn-primary',
                            onclick: 'viewPatientRecords'
                        },
                        {
                            text: 'Add Record',
                            class: 'btn btn-sm btn-success',
                            onclick: 'addPatientRecord'
                        }
                    ]);
                } else {
                    container.innerHTML = '<p class="text-muted">No approved patients found</p>';
                }
            } else {
                container.innerHTML = '<p class="text-muted">No patients found</p>';
            }
        })
        .catch(error => {
            console.error('Error loading patients:', error);
            document.getElementById('patientsTable').innerHTML = '<p class="text-danger">Error loading patients</p>';
        });
}

function loadStats() {
    fetch('<?= route('links/list') ?>&by=doctor')
        .then(response => response.json())
        .then(data => {
            if (data.links) {
                const approvedCount = data.links.filter(link => link.status === 'APPROVED').length;
                document.getElementById('totalPatients').textContent = approvedCount;
            }
        })
        .catch(error => {
            console.error('Error loading stats:', error);
        });
}

function searchPatient() {
    const email = document.getElementById('searchEmail').value;
    if (!email) {
        showAlert('Please enter a patient email', 'warning');
        return;
    }
    
    fetch(`<?= route('doctor/search-patient') ?>&email=${encodeURIComponent(email)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
            } else if (data.patient) {
                showModal('Patient Found', `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Name:</strong> ${data.patient.full_name}<br>
                            <strong>Email:</strong> ${data.patient.email}<br>
                            <strong>Status:</strong> ${data.patient.status || 'Not linked'}
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary" onclick="requestAccess('${data.patient.id}')">Request Access</button>
                    </div>
                `);
            } else {
                showAlert('Patient not found', 'warning');
            }
        })
        .catch(error => {
            showAlert('Error searching for patient', 'danger');
        });
}

function requestAccess(patientId) {
    fetch('<?= route('links/request') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ patientId: patientId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showAlert(data.error, 'danger');
        } else {
            showAlert('Access request sent successfully!', 'success');
            loadPatients();
        }
    })
    .catch(error => {
        showAlert('Error sending request', 'danger');
    });
}

function viewPatientRecords(button, patient) {
    window.location.href = `<?= route('doctor/patient-records') ?>&patientId=${patient.patient_id}`;
}

function addPatientRecord(button, patient) {
    showModal('Add Medical Record', `
        <form id="addRecordForm">
            <input type="hidden" name="patientId" value="${patient.patient_id}">
            <div class="mb-3">
                <label for="recordType" class="form-label">Record Type</label>
                <select class="form-select" id="recordType" name="type" required>
                    <option value="">Select type</option>
                    <option value="ENCOUNTER">Encounter</option>
                    <option value="LAB">Lab Result</option>
                    <option value="PRESCRIPTION">Prescription</option>
                    <option value="NOTE">Note</option>
                    <option value="VITAL">Vital Signs</option>
                    <option value="ALLERGY">Allergy</option>
                    <option value="IMAGING">Imaging</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="recordContent" class="form-label">Content (JSON)</label>
                <textarea class="form-control" id="recordContent" name="content" rows="5" required></textarea>
                <div class="form-text">Enter the medical record content as JSON</div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Add Record</button>
            </div>
        </form>
    `);
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
