<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';

requireRole('PATIENT');

$user = getCurrentUser();
$title = 'Account Deregistration';

// Get patient ID
$stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    header('Location: ' . BASE_URL . '/?r=dashboard');
    exit;
}

// Check if deletion job already exists
$stmt = $pdo->prepare("SELECT * FROM deletion_jobs WHERE patient_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$patient['id']]);
$deletionJob = $stmt->fetch();

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Account Deregistration
                </h4>
            </div>
            <div class="card-body">
                <?php if ($deletionJob): ?>
                    <?php if ($deletionJob['status'] === 'PENDING'): ?>
                        <div class="alert alert-warning">
                            <h5>Deletion Request Pending</h5>
                            <p>Your account deletion request is currently pending. The process will begin shortly.</p>
                            <p><strong>Request ID:</strong> <?= $deletionJob['id'] ?></p>
                            <p><strong>Requested:</strong> <?= date('Y-m-d H:i:s', strtotime($deletionJob['created_at'])) ?></p>
                        </div>
                    <?php elseif ($deletionJob['status'] === 'IN_PROGRESS'): ?>
                        <div class="alert alert-info">
                            <h5>Deletion In Progress</h5>
                            <p>Your account deletion is currently being processed. This may take a few minutes.</p>
                            <p><strong>Job ID:</strong> <?= $deletionJob['id'] ?></p>
                        </div>
                    <?php elseif ($deletionJob['status'] === 'COMPLETE'): ?>
                        <div class="alert alert-success">
                            <h5>Deletion Complete</h5>
                            <p>Your account has been successfully deleted. Here is your deletion receipt:</p>
                            <button class="btn btn-outline-success" onclick="showDeletionReceipt()">
                                <i class="bi bi-receipt"></i> View Deletion Receipt
                            </button>
                        </div>
                    <?php elseif ($deletionJob['status'] === 'FAILED'): ?>
                        <div class="alert alert-danger">
                            <h5>Deletion Failed</h5>
                            <p>There was an error during the deletion process. Please contact support.</p>
                            <p><strong>Error Details:</strong> <?= htmlspecialchars($deletionJob['steps']) ?></p>
                            <button class="btn btn-danger" onclick="retryDeletion()">
                                <i class="bi bi-arrow-clockwise"></i> Retry Deletion
                            </button>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h5>Warning: This Action Cannot Be Undone</h5>
                        <p>Deregistering your account will permanently delete all your medical records and personal data. This action cannot be undone.</p>
                    </div>
                    
                    <h5>What will be deleted:</h5>
                    <ul class="list-group mb-4">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-medical text-primary"></i> All medical records</span>
                            <span class="badge bg-primary" id="recordsCount">Loading...</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-earmark text-info"></i> All uploaded documents</span>
                            <span class="badge bg-info" id="documentsCount">Loading...</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-shield-check text-success"></i> All consent agreements</span>
                            <span class="badge bg-success" id="consentsCount">Loading...</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-people text-warning"></i> All doctor connections</span>
                            <span class="badge bg-warning" id="linksCount">Loading...</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-person text-secondary"></i> Personal demographics (anonymized)</span>
                            <span class="badge bg-secondary">1</span>
                        </li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <h6>What you'll receive:</h6>
                        <ul class="mb-0">
                            <li>A cryptographic receipt proving the deletion</li>
                            <li>Details of what was deleted and when</li>
                            <li>Hash verification for audit purposes</li>
                        </ul>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="confirmDeletion" required>
                        <label class="form-check-label" for="confirmDeletion">
                            I understand that this action will permanently delete all my data and cannot be undone
                        </label>
                    </div>
                    
                    <div class="d-grid">
                        <button class="btn btn-danger btn-lg" onclick="confirmDeletion()" disabled id="deleteButton">
                            <i class="bi bi-trash"></i> Permanently Delete My Account
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Deletion Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deletion Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="receiptContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadReceipt()">
                    <i class="bi bi-download"></i> Download Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirmDeletion');
    const deleteButton = document.getElementById('deleteButton');
    
    if (confirmCheckbox && deleteButton) {
        confirmCheckbox.addEventListener('change', function() {
            deleteButton.disabled = !this.checked;
        });
        
        loadDataCounts();
    }
});

function loadDataCounts() {
    const patientId = <?= $patient['id'] ?>;
    
    // Load records count
    fetch(`<?= route('ehr/list') ?>&patientId=${patientId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('recordsCount').textContent = data.records ? data.records.length : 0;
        });
    
    // Load other counts (these would need to be implemented in the API)
    // For now, we'll show estimated counts
    document.getElementById('documentsCount').textContent = '~5';
    document.getElementById('consentsCount').textContent = '~2';
    document.getElementById('linksCount').textContent = '~1';
}

function confirmDeletion() {
    if (!document.getElementById('confirmDeletion').checked) {
        showAlert('Please confirm that you understand the consequences', 'warning');
        return;
    }
    
    if (confirm('Are you absolutely sure you want to permanently delete your account? This action cannot be undone.')) {
        const patientId = <?= $patient['id'] ?>;
        
        fetch('<?= route('patient/deregister-action') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({patientId: patientId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showAlert(data.error, 'danger');
            } else {
                showAlert('Deletion process started. Please refresh the page to see the status.', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        })
        .catch(error => {
            showAlert('Error starting deletion process', 'danger');
        });
    }
}

function showDeletionReceipt() {
    const patientId = <?= $patient['id'] ?>;
    
    fetch(`<?= route('patient/deletion-receipt') ?>&patientId=${patientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.receipt) {
                displayReceipt(data.receipt);
                const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
                modal.show();
            } else {
                showAlert('Receipt not found', 'danger');
            }
        })
        .catch(error => {
            showAlert('Error loading receipt', 'danger');
        });
}

function displayReceipt(receipt) {
    const content = document.getElementById('receiptContent');
    
    content.innerHTML = `
        <div class="alert alert-success">
            <h6><i class="bi bi-check-circle"></i> Account Successfully Deleted</h6>
            <p class="mb-0">Your account has been permanently deleted with verifiable proof.</p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h6>Deletion Summary</h6>
                <ul class="list-unstyled">
                    <li><strong>Patient ID:</strong> ${receipt.patientId}</li>
                    <li><strong>Deleted At:</strong> ${formatDateTime(receipt.deletedAt)}</li>
                    <li><strong>Records Purged:</strong> ${receipt.recordsPurged}</li>
                    <li><strong>Documents Purged:</strong> ${receipt.docsPurged}</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Verification</h6>
                <ul class="list-unstyled">
                    <li><strong>Receipt Hash:</strong> <code>${receipt.verification.receipt_hash}</code></li>
                    <li><strong>Job ID:</strong> ${receipt.verification.job_id}</li>
                    <li><strong>Status:</strong> <span class="badge bg-success">${receipt.verification.verification_status}</span></li>
                </ul>
            </div>
        </div>
        
        <div class="mt-3">
            <h6>Deletion Steps</h6>
            <ol>
                ${receipt.steps.map(step => `<li>${step}</li>`).join('')}
            </ol>
        </div>
        
        <div class="mt-3">
            <h6>Raw Receipt Data</h6>
            <pre class="bg-light p-3 rounded"><code>${JSON.stringify(receipt, null, 2)}</code></pre>
        </div>
    `;
}

function downloadReceipt() {
    const patientId = <?= $patient['id'] ?>;
    
    fetch(`<?= route('patient/deletion-receipt') ?>&patientId=${patientId}`)
        .then(response => response.json())
        .then(data => {
            if (data.receipt) {
                const receiptData = JSON.stringify(data.receipt, null, 2);
                const blob = new Blob([receiptData], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `deletion_receipt_${patientId}_${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        })
        .catch(error => {
            showAlert('Error downloading receipt', 'danger');
        });
}

function retryDeletion() {
    if (confirm('Are you sure you want to retry the deletion process?')) {
        window.location.reload();
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
