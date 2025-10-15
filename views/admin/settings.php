<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/config.php';

requireRole('ADMIN');

$user = getCurrentUser();
$title = 'System Settings';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>System Settings</h2>
    <span class="badge bg-danger fs-6">ADMIN</span>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">General Settings</h5>
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    <div class="mb-3">
                        <label for="app_name" class="form-label">Application Name</label>
                        <input type="text" class="form-control" id="app_name" name="app_name" value="MediHub">
                    </div>
                    
                    <div class="mb-3">
                        <label for="k_anonymity_threshold" class="form-label">K-Anonymity Threshold</label>
                        <input type="number" class="form-control" id="k_anonymity_threshold" name="k_anonymity_threshold" value="10" min="1">
                        <div class="form-text">Minimum group size for anonymized data (default: 10)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="max_file_size" class="form-label">Maximum File Size (bytes)</label>
                        <input type="number" class="form-control" id="max_file_size" name="max_file_size" value="5242880">
                        <div class="form-text">Maximum file size for uploads (default: 5MB)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                        <input type="text" class="form-control" id="allowed_file_types" name="allowed_file_types" value="pdf,jpg,jpeg,png,gif">
                        <div class="form-text">Comma-separated list of allowed file extensions</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <strong>PHP Version:</strong>
                    </div>
                    <div class="col-6">
                        <?= PHP_VERSION ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Database:</strong>
                    </div>
                    <div class="col-6">
                        MySQL
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Server:</strong>
                    </div>
                    <div class="col-6">
                        <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Upload Max:</strong>
                    </div>
                    <div class="col-6">
                        <?= ini_get('upload_max_filesize') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
    
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveSettings();
    });
});

function loadSettings() {
    // Load current settings from database
    fetch('<?= route('admin/settings-api') ?>')
        .then(response => response.json())
        .then(data => {
            if (data.settings) {
                data.settings.forEach(setting => {
                    const element = document.getElementById(setting.setting_key);
                    if (element) {
                        element.value = setting.setting_value;
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading settings:', error);
        });
}

function saveSettings() {
    const formData = new FormData(document.getElementById('settingsForm'));
    const data = Object.fromEntries(formData.entries());
    
    fetch('<?= route('admin/settings-update') ?>', {
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
            showAlert('Settings saved successfully!', 'success');
        }
    })
    .catch(error => {
        showAlert('Error saving settings', 'danger');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
