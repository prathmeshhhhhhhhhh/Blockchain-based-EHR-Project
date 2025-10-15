<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';

requireAuth();

$user = getCurrentUser();
$title = 'My Profile';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>My Profile</h2>
    <span class="badge bg-<?= $user['role'] === 'ADMIN' ? 'danger' : ($user['role'] === 'DOCTOR' ? 'success' : 'primary') ?> fs-6">
        <?= $user['role'] ?>
    </span>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <form id="profileForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($user['role'] === 'PATIENT'): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" value="<?= $user['dob'] ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="Male" <?= $user['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $user['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $user['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user['role'] === 'DOCTOR'): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reg_no" class="form-label">Registration Number</label>
                                <input type="text" class="form-control" id="reg_no" name="reg_no" value="<?= htmlspecialchars($user['reg_no']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="organization" class="form-label">Organization</label>
                                <input type="text" class="form-control" id="organization" name="organization" value="<?= htmlspecialchars($user['organization']) ?>">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Account Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6">
                        <strong>User ID:</strong>
                    </div>
                    <div class="col-6">
                        <?= $user['id'] ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Role:</strong>
                    </div>
                    <div class="col-6">
                        <?= $user['role'] ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <strong>Member Since:</strong>
                    </div>
                    <div class="col-6">
                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-warning">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        e.preventDefault();
        updateProfile();
    });
    
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        changePassword();
    });
});

function updateProfile() {
    const formData = new FormData(document.getElementById('profileForm'));
    const data = Object.fromEntries(formData.entries());
    
    fetch('<?= route('profile/update') ?>', {
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
            showAlert('Profile updated successfully!', 'success');
        }
    })
    .catch(error => {
        showAlert('Error updating profile', 'danger');
    });
}

function changePassword() {
    const formData = new FormData(document.getElementById('passwordForm'));
    const data = Object.fromEntries(formData.entries());
    
    if (data.new_password !== data.confirm_password) {
        showAlert('New passwords do not match', 'danger');
        return;
    }
    
    fetch('<?= route('profile/change-password') ?>', {
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
            showAlert('Password changed successfully!', 'success');
            document.getElementById('passwordForm').reset();
        }
    })
    .catch(error => {
        showAlert('Error changing password', 'danger');
    });
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
