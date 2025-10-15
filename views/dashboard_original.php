<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

requireAuth();

$user = getCurrentUser();
$title = 'Dashboard';

// Get patient ID if user is a patient
$patientId = null;
if (hasRole('PATIENT')) {
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $patient = $stmt->fetch();
    $patientId = $patient['id'] ?? null;
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h2>
    <span class="badge bg-<?= $user['role'] === 'ADMIN' ? 'danger' : ($user['role'] === 'DOCTOR' ? 'success' : 'primary') ?> fs-6">
        <?= $user['role'] ?>
    </span>
</div>

<?php if (hasRole('PATIENT')): ?>
<!-- Patient Dashboard -->
<div class="row g-4">
    <div class="col-md-3">
        <div class="card text-center dashboard-card"></div>
            <div class="card-body">
                <i class="bi bi-file-medical text-primary" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">My Records</h5>
                <p class="card-text">View and manage your medical records</p>
                <a href="<?= route('patient/records') ?>" class="btn btn-primary">View Records</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-shield-check text-success" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Consents</h5>
                <p class="card-text">Manage doctor access permissions</p>
                <a href="<?= route('patient/consents') ?>" class="btn btn-success">Manage Consents</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-person-plus text-warning" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Doctor Requests</h5>
                <p class="card-text">Review access requests from doctors</p>
                <a href="<?= route('patient/requests') ?>" class="btn btn-warning">View Requests</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-trash text-danger" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Deregister</h5>
                <p class="card-text">Request account deletion</p>
                <a href="<?= route('patient/deregister') ?>" class="btn btn-danger">Deregister</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-file-medical me-2"></i>Recent Records</h5>
            </div>
            <div class="card-body">
                <div id="recentRecords">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading recent records...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-shield-check me-2"></i>Active Consents</h5>
            </div>
            <div class="card-body">
                <div id="activeConsents">
                    <div class="text-center py-4">
                        <div class="spinner-border text-success" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading active consents...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif (hasRole('DOCTOR')): ?>
<!-- Doctor Dashboard -->
<div class="row">
    <div class="col-md-4">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">My Patients</h5>
                <p class="card-text">View your approved patients</p>
                <a href="<?= route('doctor/patients') ?>" class="btn btn-primary">View Patients</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-search text-success" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Search Patient</h5>
                <p class="card-text">Find and request access to patients</p>
                <a href="<?= route('doctor/search') ?>" class="btn btn-success">Search</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-file-plus text-info" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Add Record</h5>
                <p class="card-text">Create new medical records</p>
                <button class="btn btn-info" onclick="showAddRecordModal()">Add Record</button>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div id="recentActivity">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Pending Requests</h5>
            </div>
            <div class="card-body">
                <div id="pendingRequests">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif (hasRole('ADMIN')): ?>
<!-- Admin Dashboard -->
<div class="row">
    <div class="col-md-3">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-graph-up text-primary" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Analytics</h5>
                <p class="card-text">View system metrics and reports</p>
                <a href="<?= route('admin/overview') ?>" class="btn btn-primary">View Analytics</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-list-check text-success" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Audit Log</h5>
                <p class="card-text">Monitor system activity</p>
                <a href="<?= route('admin/audit') ?>" class="btn btn-success">View Audit</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center dashboard-card">
            <div class="card-body">
                <i class="bi bi-people text-info" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Users</h5>
                <p class="card-text">Manage user accounts</p>
                <a href="<?= route('admin/users') ?>" class="btn btn-info">Manage Users</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
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

<div class="row mt-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">System Overview</h5>
            </div>
            <div class="card-body">
                <div id="systemMetrics">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (hasRole('PATIENT')): ?>
    loadRecentRecords();
    loadActiveConsents();
    <?php elseif (hasRole('DOCTOR')): ?>
    loadRecentActivity();
    loadPendingRequests();
    <?php elseif (hasRole('ADMIN')): ?>
    loadSystemMetrics();
    <?php endif; ?>
});

<?php if (hasRole('PATIENT')): ?>
function loadRecentRecords() {
    fetch('<?= route('ehr/list') ?>&patientId=<?= $patientId ?>&limit=5')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('recentRecords');
            if (data.records && data.records.length > 0) {
                container.innerHTML = data.records.map(record => `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>${record.type}</strong>
                            <small class="text-muted d-block">${new Date(record.recorded_at).toLocaleDateString()}</small>
                        </div>
                        <span class="badge bg-secondary">${record.created_by_name || 'Unknown'}</span>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">No records found</p>';
            }
        })
        .catch(error => {
            document.getElementById('recentRecords').innerHTML = '<p class="text-danger">Error loading records</p>';
        });
}

function loadActiveConsents() {
    fetch('<?= route('consents/list') ?>&patientId=<?= $patientId ?>')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('activeConsents');
            if (data.consents && data.consents.length > 0) {
                const activeConsents = data.consents.filter(c => c.status === 'ACTIVE');
                container.innerHTML = activeConsents.map(consent => `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>${consent.doctor_name}</strong>
                            <small class="text-muted d-block">${consent.purpose} - ${consent.scopes.join(', ')}</small>
                        </div>
                        <span class="badge bg-success">Active</span>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">No active consents</p>';
            }
        })
        .catch(error => {
            document.getElementById('activeConsents').innerHTML = '<p class="text-danger">Error loading consents</p>';
        });
}
<?php endif; ?>

<?php if (hasRole('DOCTOR')): ?>
function loadRecentActivity() {
    // This would load recent EHR records created by the doctor
    document.getElementById('recentActivity').innerHTML = '<p class="text-muted">Recent activity will be shown here</p>';
}

function loadPendingRequests() {
    fetch('<?= route('links/list') ?>&by=doctor')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('pendingRequests');
            if (data.links && data.links.length > 0) {
                const pending = data.links.filter(link => link.status === 'REQUESTED');
                container.innerHTML = pending.map(link => `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>${link.patient_name}</strong>
                            <small class="text-muted d-block">${new Date(link.created_at).toLocaleDateString()}</small>
                        </div>
                        <span class="badge bg-warning">Pending</span>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">No pending requests</p>';
            }
        })
        .catch(error => {
            document.getElementById('pendingRequests').innerHTML = '<p class="text-danger">Error loading requests</p>';
        });
}
<?php endif; ?>

<?php if (hasRole('ADMIN')): ?>
function loadSystemMetrics() {
    fetch('<?= route('admin/metrics') ?>')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('systemMetrics');
            if (data.metrics) {
                const metrics = data.metrics;
                container.innerHTML = `
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-primary">${metrics.total_users || 0}</h3>
                                <p class="text-muted">Total Users</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-success">${metrics.total_patients || 0}</h3>
                                <p class="text-muted">Patients</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-info">${metrics.total_doctors || 0}</h3>
                                <p class="text-muted">Doctors</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h3 class="text-warning">${metrics.total_records || 0}</h3>
                                <p class="text-muted">Medical Records</p>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                container.innerHTML = '<p class="text-danger">Error loading metrics</p>';
            }
        })
        .catch(error => {
            document.getElementById('systemMetrics').innerHTML = '<p class="text-danger">Error loading metrics</p>';
        });
}
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
