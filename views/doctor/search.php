<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('DOCTOR');

$user = getCurrentUser();
$title = 'Search Patient';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Search Patient</h2>
    <span class="badge bg-success fs-6">DOCTOR</span>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Search by Email</h5>
            </div>
            <div class="card-body">
                <form id="searchForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">Patient Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="Enter patient's email address">
                        <div class="form-text">Enter the exact email address of the patient you want to request access to</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Search Tips</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Use the exact email address</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Patient must be registered in the system</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> You'll need to wait for patient approval</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Check your dashboard for responses</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Search Results</h5>
            </div>
            <div class="card-body">
                <div id="searchResults">
                    <p class="text-muted text-center">Enter an email address to search for a patient</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        searchPatient();
    });
});

function searchPatient() {
    const email = document.getElementById('email').value;
    if (!email) {
        showAlert('Please enter a patient email', 'warning');
        return;
    }
    
    const resultsContainer = document.getElementById('searchResults');
    resultsContainer.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Searching...</span>
            </div>
            <p class="mt-2">Searching for patient...</p>
        </div>
    `;
    
    fetch(`<?= route('doctor/search-patient') ?>&email=${encodeURIComponent(email)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                resultsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> ${data.error}
                    </div>
                `;
            } else if (data.patient) {
                const patient = data.patient;
                resultsContainer.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5>${patient.full_name}</h5>
                                    <p class="text-muted">${patient.email}</p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge ${patient.status === 'APPROVED' ? 'bg-success' : 
                                                          patient.status === 'REQUESTED' ? 'bg-warning' : 'bg-secondary'}">
                                            ${patient.status || 'Not Linked'}
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    ${patient.status === 'APPROVED' ? 
                                        `<a href="<?= route('doctor/patient-records') ?>&patientId=${patient.patient_id}" class="btn btn-primary">
                                            <i class="bi bi-file-medical"></i> View Records
                                        </a>` :
                                        patient.status === 'REQUESTED' ?
                                        `<button class="btn btn-warning" disabled>
                                            <i class="bi bi-hourglass-split"></i> Pending Approval
                                        </button>` :
                                        `<button class="btn btn-success" onclick="requestAccess('${patient.patient_id}')">
                                            <i class="bi bi-person-plus"></i> Request Access
                                        </button>`
                                    }
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                resultsContainer.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-search"></i> No patient found with email: ${email}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            resultsContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Error searching for patient. Please try again.
                </div>
            `;
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
            showAlert('Access request sent successfully! The patient will be notified.', 'success');
            // Refresh the search results
            document.getElementById('searchForm').dispatchEvent(new Event('submit'));
        }
    })
    .catch(error => {
        showAlert('Error sending request', 'danger');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
