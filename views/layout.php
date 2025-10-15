<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'MediHub' ?> - Electronic Health Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= route('dashboard') ?>">
                <i class="bi bi-hospital"></i> MediHub
            </a>
            
            <?php if (isLoggedIn()): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('dashboard') ?>">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                    </li>
                    
                    <?php if (hasRole('PATIENT')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('patient/records') ?>">
                            <i class="bi bi-file-medical"></i> My Records
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('patient/consents') ?>">
                            <i class="bi bi-shield-check"></i> Consents
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('patient/requests') ?>">
                            <i class="bi bi-person-plus"></i> Doctor Requests
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('DOCTOR')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('doctor/patients') ?>">
                            <i class="bi bi-people"></i> My Patients
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('doctor/search') ?>">
                            <i class="bi bi-search"></i> Search Patient
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('ADMIN')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('admin/overview') ?>">
                            <i class="bi bi-graph-up"></i> Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= route('admin/audit') ?>">
                            <i class="bi bi-list-check"></i> Audit Log
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars(getCurrentUser()['full_name'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= route('profile') ?>">
                                <i class="bi bi-person"></i> Profile
                            </a></li>
                            <?php if (hasRole('PATIENT')): ?>
                            <li><a class="dropdown-item" href="<?= route('patient/deregister') ?>">
                                <i class="bi bi-trash"></i> Deregister
                            </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="logout()">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <main class="container mt-4">
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>
        
        <?= $content ?? '' ?>
    </main>

    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="text-muted mb-0">&copy; 2024 MediHub. Electronic Health Records System.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
    // Helper function for API calls
    function apiUrl(route) {
        const baseUrl = '<?= BASE_URL ?>/?r=';
        if (route.includes('?')) {
            // If route already has query parameters, replace ? with &
            return baseUrl + route.replace('?', '&');
        }
        return baseUrl + route;
    }
    // Expose BASE_URL for redirects
    window.BASE_URL = '<?= BASE_URL ?>';
    </script>
    <?= $scripts ?? '' ?>
</body>
</html>
