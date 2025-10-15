<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/config.php';

requireRole('ADMIN');

$user = getCurrentUser();
$title = 'User Management';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>User Management</h2>
    <span class="badge bg-danger fs-6">ADMIN</span>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Users</h5>
    </div>
    <div class="card-body">
        <div id="usersTable">
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
    loadUsers();
});

function loadUsers() {
    fetch('<?= route('admin/users-api') ?>')
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('usersTable');
            if (data.users && data.users.length > 0) {
                const columns = [
                    { key: 'id', title: 'ID' },
                    { key: 'full_name', title: 'Name' },
                    { key: 'email', title: 'Email' },
                    { key: 'role', title: 'Role' },
                    { key: 'created_at', title: 'Created', render: (value) => new Date(value).toLocaleDateString() }
                ];
                
                createDataTable('usersTable', data.users, columns, [
                    {
                        text: 'View',
                        class: 'btn btn-sm btn-outline-primary',
                        onclick: 'viewUser'
                    },
                    {
                        text: 'Edit',
                        class: 'btn btn-sm btn-outline-secondary',
                        onclick: 'editUser'
                    }
                ]);
            } else {
                container.innerHTML = '<p class="text-muted">No users found</p>';
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
            document.getElementById('usersTable').innerHTML = '<p class="text-danger">Error loading users</p>';
        });
}

function viewUser(button, user) {
    showModal('User Details', `
        <div class="row">
            <div class="col-md-6">
                <strong>Name:</strong> ${user.full_name}<br>
                <strong>Email:</strong> ${user.email}<br>
                <strong>Role:</strong> ${user.role}<br>
                <strong>Created:</strong> ${new Date(user.created_at).toLocaleString()}
            </div>
        </div>
    `);
}

function editUser(button, user) {
    showModal('Edit User', `
        <form id="editUserForm">
            <input type="hidden" name="id" value="${user.id}">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="full_name" name="full_name" value="${user.full_name}" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="${user.email}" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="PATIENT" ${user.role === 'PATIENT' ? 'selected' : ''}>Patient</option>
                    <option value="DOCTOR" ${user.role === 'DOCTOR' ? 'selected' : ''}>Doctor</option>
                    <option value="ADMIN" ${user.role === 'ADMIN' ? 'selected' : ''}>Admin</option>
                </select>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    `);
    
    // Add form submission handler
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        updateUser(user.id);
    });
}

function updateUser(userId) {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('<?= route('admin/users-update') ?>', {
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
            showAlert('User updated successfully!', 'success');
            bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
            loadUsers(); // Reload the users table
        }
    })
    .catch(error => {
        showAlert('Error updating user', 'danger');
    });
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
