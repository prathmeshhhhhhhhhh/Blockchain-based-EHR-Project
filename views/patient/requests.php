<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('PATIENT');

$user = getCurrentUser();
$title = 'Doctor Access Requests';

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
    <h2>Doctor Access Requests</h2>
    <button class="btn btn-outline-primary" onclick="loadRequests()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
</div>

<!-- Search Doctors Section -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Search & Connect with Doctors</h5>
            </div>
            <div class="card-body">
                <form id="searchDoctorForm">
                    <div class="mb-3">
                        <label for="doctorEmail" class="form-label">Doctor Email Address</label>
                        <input type="email" class="form-control" id="doctorEmail" name="email" required placeholder="Enter doctor's email address">
                        <div class="form-text">Enter the exact email address of the doctor you want to connect with</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search Doctor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">How it Works</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Search for doctors by email</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Send connection requests</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Wait for doctor approval</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Manage your connections</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div id="requestsContainer">
    <div class="text-center">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadRequests();
    
    // Handle doctor search form
    document.getElementById('searchDoctorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        searchDoctor();
    });
});

function loadRequests() {
    fetch('<?= route('links/list') ?>&by=patient')
        .then(response => response.json())
        .then(data => {
            displayRequests(data.links || []);
        })
        .catch(error => {
            document.getElementById('requestsContainer').innerHTML = 
                '<div class="alert alert-danger">Error loading requests</div>';
        });
}

function displayRequests(links) {
    const container = document.getElementById('requestsContainer');
    
    if (links.length === 0) {
        container.innerHTML = '<div class="alert alert-info">No access requests found</div>';
        return;
    }
    
    const requestsHtml = links.map(link => `
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title">
                            <span class="badge bg-${getStatusColor(link.status)}">${link.status}</span>
                            ${link.doctor_name}
                        </h5>
                        <p class="card-text">
                            <strong>Email:</strong> ${link.doctor_email}<br>
                            <strong>Registration:</strong> ${link.reg_no || 'Not provided'}<br>
                            <strong>Organization:</strong> ${link.organization || 'Not provided'}<br>
                            <strong>Requested:</strong> ${formatDateTime(link.created_at)}
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        ${link.status === 'REQUESTED' ? `
                            <div class="btn-group-vertical" role="group">
                                <button class="btn btn-success btn-sm" onclick="approveRequest(${link.id})">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="rejectRequest(${link.id})">
                                    <i class="bi bi-x-circle"></i> Reject
                                </button>
                            </div>
                        ` : `
                            <span class="text-muted">${link.status}</span>
                        `}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    container.innerHTML = requestsHtml;
}

function getStatusColor(status) {
    const colors = {
        'REQUESTED': 'warning',
        'APPROVED': 'success',
        'REVOKED': 'danger'
    };
    return colors[status] || 'secondary';
}

function approveRequest(linkId) {
    if (confirm('Are you sure you want to approve this access request?')) {
        updateRequestStatus(linkId, true);
    }
}

function rejectRequest(linkId) {
    if (confirm('Are you sure you want to reject this access request?')) {
        updateRequestStatus(linkId, false);
    }
}

function updateRequestStatus(linkId, approved) {
    fetch('<?= route('links/approve') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            linkId: linkId,
            approved: approved
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showAlert(data.error, 'danger');
        } else {
            showAlert(data.message, 'success');
            loadRequests();
        }
    })
    .catch(error => {
        showAlert('Error updating request', 'danger');
    });
}

function searchDoctor() {
    const email = document.getElementById('doctorEmail').value;
    if (!email) {
        showAlert('Please enter a doctor email', 'warning');
        return;
    }
    
    fetch(`<?= route('patient/search-doctor') ?>&email=${encodeURIComponent(email)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
            } else if (data.doctor) {
                showDoctorModal(data.doctor);
            } else {
                showAlert('Doctor not found', 'warning');
            }
        })
        .catch(error => {
            showAlert('Error searching for doctor', 'danger');
        });
}

function showDoctorModal(doctor) {
    const statusColor = getStatusColor(doctor.status);
    const statusText = doctor.status === 'Not Linked' ? 'Not Connected' : doctor.status;
    
    showModal('Doctor Found', `
        <div class="row">
            <div class="col-md-6">
                <strong>Name:</strong> ${doctor.full_name}<br>
                <strong>Email:</strong> ${doctor.email}<br>
                <strong>Registration:</strong> ${doctor.reg_no || 'Not provided'}<br>
                <strong>Organization:</strong> ${doctor.organization || 'Not provided'}
            </div>
            <div class="col-md-6">
                <strong>Status:</strong> <span class="badge bg-${statusColor}">${statusText}</span>
            </div>
        </div>
        <div class="mt-3">
            ${doctor.status === 'Not Linked' ? 
                `<button class="btn btn-primary" onclick="requestDoctorAccess('${doctor.doctor_id}')">Request Access</button>` :
                `<p class="text-muted">Connection already exists</p>`
            }
        </div>
    `);
}

function requestDoctorAccess(doctorId) {
    // This would need to be implemented - for now just show a message
    showAlert('Doctor access request functionality will be implemented soon. For now, doctors need to request access to patients.', 'info');
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
