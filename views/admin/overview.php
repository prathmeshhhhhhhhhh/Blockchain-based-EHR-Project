<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/config.php';

requireRole('ADMIN');

$user = getCurrentUser();
$title = 'Admin Overview';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>System Overview</h2>
    <span class="badge bg-danger fs-6">ADMIN</span>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Total Users</h5>
                <p class="card-text" id="totalUsers">Loading...</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-file-medical text-success" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Medical Records</h5>
                <p class="card-text" id="totalRecords">Loading...</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-shield-check text-info" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">Active Consents</h5>
                <p class="card-text" id="activeConsents">Loading...</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-graph-up text-warning" style="font-size: 2rem;"></i>
                <h5 class="card-title mt-2">System Health</h5>
                <p class="card-text" id="systemHealth">Loading...</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Activity</h5>
                <a href="<?= route('admin/audit-log') ?>" class="btn btn-sm btn-outline-primary">Open Audit Log</a>
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
                <h5 class="mb-0">System Metrics</h5>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSystemMetrics();
    loadRecentActivity();
    // Auto-refresh recent activity periodically
    setInterval(loadRecentActivity, 15000);
});

function loadSystemMetrics() {
    fetch('<?= route('admin/metrics') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.metrics) {
                const metrics = data.metrics;
                document.getElementById('totalUsers').textContent = metrics.total_users || 0;
                document.getElementById('totalRecords').textContent = metrics.total_records || 0;
                document.getElementById('activeConsents').textContent = metrics.active_consents || 0;
                document.getElementById('systemHealth').textContent = 'Healthy';
                
                // Display detailed metrics
                const container = document.getElementById('systemMetrics');
                container.innerHTML = `
                    <div class="row">
                        <div class="col-6">
                            <strong>Patients:</strong> ${metrics.total_patients || 0}
                        </div>
                        <div class="col-6">
                            <strong>Doctors:</strong> ${metrics.total_doctors || 0}
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <strong>Records by Type:</strong>
                        </div>
                    </div>
                    <div class="mt-2">
                        ${(metrics.records_by_type || []).map(record => 
                            `<span class="badge bg-secondary me-1">${record.type}: ${record.count}</span>`
                        ).join('')}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading metrics:', error);
            document.getElementById('systemMetrics').innerHTML = '<p class="text-danger">Error loading metrics</p>';
        });
}

function loadRecentActivity() {
    fetch('<?= route('admin/audit') ?>&limit=10', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('recentActivity');
            // Define patient-doctor interaction actions only
            const interactionActions = new Set([
                'ACCESS_REQUEST',
                'ACCESS_RESPONSE',
                'CONSENT_CREATED',
                'CONSENT_REVOKED',
                'RECORD_ASSIGNED',
                'EHR_VIEW',
                'EHR_CREATE',
                'EHR_UPDATE',
                'DOCUMENT_UPLOAD',
                'DOCUMENT_DOWNLOAD'
            ]);

            const allEntries = data.audit_entries || [];
            let entries = allEntries.filter(e => interactionActions.has(e.action));
            // If no interaction entries exist, fallback to latest entries
            if (entries.length === 0) entries = allEntries;
            // Show only the latest 4 entries
            entries = entries.slice(0, 4);

            if (entries.length > 0) {
                container.innerHTML = entries.map(entry => `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong>${entry.action.replace(/_/g, ' ')}</strong>
                            <small class="text-muted d-block">${new Date(entry.ts).toLocaleString()}</small>
                        </div>
                        <span class="badge bg-info">Doctor–Patient Interaction</span>
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<p class="text-muted">No recent activity</p>';
            }
        })
        .catch(error => {
            console.error('Error loading activity:', error);
            document.getElementById('recentActivity').innerHTML = '<p class="text-danger">Error loading activity</p>';
        });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
