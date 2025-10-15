<?php
$title = 'Welcome';
ob_start();
?>

<div class="hero-section bg-primary text-white py-5 rounded">
    <div class="container text-center">
        <h1 class="display-4 fw-bold mb-4">
            <i class="bi bi-hospital"></i> MediHub
        </h1>
        <p class="lead mb-4">Secure Electronic Health Records Management System</p>
        <p class="mb-4">Empowering patients with control over their medical data while enabling healthcare providers to deliver better care.</p>
        
        <?php if (!isLoggedIn()): ?>
        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            <a href="<?= route('login') ?>" class="btn btn-light btn-lg me-md-2">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </a>
            <a href="<?= route('register') ?>" class="btn btn-outline-light btn-lg">
                <i class="bi bi-person-plus"></i> Register
            </a>
        </div>
        <?php else: ?>
        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
            <a href="<?= route('dashboard') ?>" class="btn btn-light btn-lg">
                <i class="bi bi-speedometer2"></i> Go to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-shield-check text-primary" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Secure & Private</h5>
                <p class="card-text">Your medical data is protected with industry-standard encryption and you maintain full control over who can access it.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-people text-success" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Consent-Based Access</h5>
                <p class="card-text">Grant specific permissions to healthcare providers for different types of data and time periods.</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-file-medical text-info" style="font-size: 3rem;"></i>
                <h5 class="card-title mt-3">Complete Records</h5>
                <p class="card-text">Store and manage all your medical records, lab results, prescriptions, and documents in one place.</p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">For Patients</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Complete control over your medical data</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Grant and revoke access to healthcare providers</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> View and manage all your medical records</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Upload and organize medical documents</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Request complete data deletion when needed</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">For Healthcare Providers</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Request access to patient records</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> View records within granted permissions</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Add new medical records and notes</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Upload and manage patient documents</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success"></i> Comprehensive audit trail of all actions</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
