<?php
/**
 * Improved Patient Dashboard
 */

$title = 'Dashboard';
ob_start();
?>

<div class="container-fluid">
    <!-- Welcome Section with Profile -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="profile-avatar me-3">
                        <div class="avatar-circle">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <div>
                        <h2 class="mb-1">Welcome back, <?= htmlspecialchars($user['full_name'] ?? 'Patient') ?>!</h2>
                        <p class="text-muted mb-0">Here's your health summary for today</p>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary fs-6 me-3"><?= $user['role'] ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($user['full_name'] ?? 'User') ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= route('profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="<?= route('settings') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= route('logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (hasRole('PATIENT')): ?>
    <!-- Health Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="card-body text-center">
                    <i class="bi bi-file-medical text-primary mb-2" style="font-size: 1.5rem;"></i>
                    <h4 class="mb-1" id="totalRecords">0</h4>
                    <p class="text-muted mb-0">Medical Records</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="card-body text-center">
                    <i class="bi bi-shield-check text-success mb-2" style="font-size: 1.5rem;"></i>
                    <h4 class="mb-1" id="activeConsentsCount">0</h4>
                    <p class="text-muted mb-0">Active Consents</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="card-body text-center">
                    <i class="bi bi-person-plus text-warning mb-2" style="font-size: 1.5rem;"></i>
                    <h4 class="mb-1" id="pendingRequests">0</h4>
                    <p class="text-muted mb-0">Pending Requests</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card metric-card">
                <div class="card-body text-center">
                    <i class="bi bi-people text-info mb-2" style="font-size: 1.5rem;"></i>
                    <h4 class="mb-1" id="linkedDoctors">0</h4>
                    <p class="text-muted mb-0">Linked Doctors</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Action Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card action-card">
                <div class="card-body d-flex align-items-center">
                    <div class="action-icon me-3">
                        <i class="bi bi-file-medical text-primary" style="font-size: 2.5rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">My Medical Records</h5>
                        <p class="card-text text-muted mb-2">View and manage your medical records, test results, and health documents</p>
                        <a href="<?= route('patient/records') ?>" class="btn btn-primary btn-lg">
                            <i class="bi bi-eye me-2"></i>View Records
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card action-card">
                <div class="card-body d-flex align-items-center">
                    <div class="action-icon me-3">
                        <i class="bi bi-shield-check text-success" style="font-size: 2.5rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">Consent Management</h5>
                        <p class="card-text text-muted mb-2">Control which doctors can access your medical information</p>
                        <a href="<?= route('patient/consents') ?>" class="btn btn-success btn-lg">
                            <i class="bi bi-gear me-2"></i>Manage Consents
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card action-card">
                <div class="card-body d-flex align-items-center">
                    <div class="action-icon me-3">
                        <i class="bi bi-person-plus text-warning" style="font-size: 2.5rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">Doctor Access Requests</h5>
                        <p class="card-text text-muted mb-2">Review and approve requests from doctors to access your records</p>
                        <a href="<?= route('patient/requests') ?>" class="btn btn-warning btn-lg">
                            <i class="bi bi-list-check me-2"></i>View Requests
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card action-card">
                <div class="card-body d-flex align-items-center">
                    <div class="action-icon me-3">
                        <i class="bi bi-trash text-danger" style="font-size: 2.5rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="card-title mb-1">Account Management</h5>
                        <p class="card-text text-muted mb-2">Request account deletion and data removal</p>
                        <a href="<?= route('patient/deregister') ?>" class="btn btn-danger btn-lg">
                            <i class="bi bi-person-x me-2"></i>Deregister Account
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Medical Records</h5>
                </div>
                <div class="card-body">
                    <div id="recentRecords">
                        <div class="text-center py-5">
                            <i class="bi bi-file-medical text-muted" style="font-size: 3rem;"></i>
                            <h5 class="text-muted mt-3">No medical records yet</h5>
                            <p class="text-muted">Upload your first medical record or request your doctor to add one.</p>
                            <a href="<?= route('patient/records') ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add Your First Record
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Active Consents</h5>
                </div>
                <div class="card-body">
                    <div id="activeConsents">
                        <div class="text-center py-4">
                            <i class="bi bi-shield-check text-muted" style="font-size: 2.5rem;"></i>
                            <h6 class="text-muted mt-2">No active consents</h6>
                            <p class="text-muted small">Grant consent to doctors to access your records.</p>
                            <a href="<?= route('patient/consents') ?>" class="btn btn-success btn-sm">
                                <i class="bi bi-plus me-1"></i>Manage Consents
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif (hasRole('DOCTOR')): ?>
    <!-- Doctor Dashboard -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card text-center dashboard-card">
                <div class="card-body">
                    <i class="bi bi-search text-primary" style="font-size: 2rem;"></i>
                    <h5 class="card-title mt-2">Search Patients</h5>
                    <p class="card-text">Find and connect with patients</p>
                    <a href="<?= route('doctor/search') ?>" class="btn btn-primary">Search</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center dashboard-card">
                <div class="card-body">
                    <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                    <h5 class="card-title mt-2">My Patients</h5>
                    <p class="card-text">Manage your approved patients</p>
                    <a href="<?= route('doctor/patients') ?>" class="btn btn-success">View Patients</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center dashboard-card">
                <div class="card-body">
                    <i class="bi bi-file-medical text-info" style="font-size: 2rem;"></i>
                    <h5 class="card-title mt-2">Add Records</h5>
                    <p class="card-text">Create new medical records</p>
                    <a href="<?= route('ehr/create') ?>" class="btn btn-info">Add Record</a>
                </div>
            </div>
        </div>
    </div>

    <?php elseif (hasRole('ADMIN')): ?>
    <!-- Admin Dashboard -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card text-center dashboard-card">
                <div class="card-body">
                    <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                    <h5 class="card-title mt-2">User Management</h5>
                    <p class="card-text">Manage users and permissions</p>
                    <a href="<?= route('admin/users') ?>" class="btn btn-primary">Manage Users</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center dashboard-card">
                <div class="card-body">
                    <i class="bi bi-graph-up text-success" style="font-size: 2rem;"></i>
                    <h5 class="card-title mt-2">Analytics</h5>
                    <p class="card-text">View system analytics</p>
                    <a href="<?= route('admin/overview') ?>" class="btn btn-success">View Analytics</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card text-center dashboard-card">
                <div class="card-body">
                    <i class="bi bi-gear text-warning" style="font-size: 2rem;"></i>
                    <h5 class="card-title mt-2">Settings</h5>
                    <p class="card-text">System configuration</p>
                    <a href="<?= route('admin/settings') ?>" class="btn btn-warning">Settings</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Load dashboard data
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
});

function loadDashboardData() {
    // Load recent records
    fetch(apiUrl('ehr/list?limit=5'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.records) {
                displayRecentRecords(data.records);
                document.getElementById('totalRecords').textContent = data.records.length;
            }
        })
        .catch(error => console.error('Error loading records:', error));

    // Load active consents
    fetch(apiUrl('consents/list'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.consents) {
                displayActiveConsents(data.consents);
                document.getElementById('activeConsentsCount').textContent = data.consents.length;
            }
        })
        .catch(error => console.error('Error loading consents:', error));

    // Load pending requests
    fetch(apiUrl('links/list'))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.links) {
                const pendingCount = data.links.filter(link => link.status === 'PENDING').length;
                document.getElementById('pendingRequests').textContent = pendingCount;
            }
        })
        .catch(error => console.error('Error loading requests:', error));
}

function displayRecentRecords(records) {
    const container = document.getElementById('recentRecords');
    
    if (records.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="bi bi-file-medical text-muted" style="font-size: 3rem;"></i>
                <h5 class="text-muted mt-3">No medical records yet</h5>
                <p class="text-muted">Upload your first medical record or request your doctor to add one.</p>
                <a href="${apiUrl('patient/records')}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Add Your First Record
                </a>
            </div>
        `;
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    records.forEach(record => {
        const date = new Date(record.recorded_at).toLocaleDateString();
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${record.type}</h6>
                    <small class="text-muted">${date}</small>
                </div>
                <span class="badge bg-primary">${record.type}</span>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

function displayActiveConsents(consents) {
    const container = document.getElementById('activeConsents');
    
    if (consents.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-shield-check text-muted" style="font-size: 2.5rem;"></i>
                <h6 class="text-muted mt-2">No active consents</h6>
                <p class="text-muted small">Grant consent to doctors to access your records.</p>
                <a href="${apiUrl('patient/consents')}" class="btn btn-success btn-sm">
                    <i class="bi bi-plus me-1"></i>Manage Consents
                </a>
            </div>
        `;
        return;
    }

    let html = '<div class="list-group list-group-flush">';
    consents.forEach(consent => {
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">${consent.doctor_name}</h6>
                    <small class="text-muted">${consent.purpose}</small>
                </div>
                <span class="badge bg-success">Active</span>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
