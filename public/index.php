<?php
session_start();
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if database connection failed
if (isset($db_error)) {
    // Show setup page if database is not available
    if ($_GET['r'] === 'setup' || !isset($_GET['r'])) {
        include '../setup_database.php';
        exit;
    }
}

// Simple router based on ?r= parameter
$route = $_GET['r'] ?? 'home';

// Route mapping
$routes = [
    'home' => '../views/home.php',
    'register' => '../views/register.php',
    'login' => '../views/login.php',
    'dashboard' => '../views/dashboard.php',
    'me' => '../controllers/auth.php',
    'auth/register' => '../controllers/auth.php',
    'auth/login' => '../controllers/auth.php',
    'auth/logout' => '../controllers/auth.php',
    'links/request' => '../controllers/links.php',
    'links/approve' => '../controllers/links.php',
    'links/list' => '../controllers/links.php',
    'consents/create' => '../controllers/consents.php',
    'consents/revoke' => '../controllers/consents.php',
    'consents/list' => '../controllers/consents.php',
    'ehr/create' => '../controllers/ehr.php',
    'ehr/list' => '../controllers/ehr.php',
    'ehr/get' => '../controllers/ehr.php',
    'ehr/update' => '../controllers/ehr.php',
    'ehr/delete' => '../controllers/ehr.php',
    'ehr/assign' => '../controllers/ehr.php',
    'doc/upload' => '../controllers/documents.php',
    'doc/download' => '../controllers/documents.php',
    'doc/delete' => '../controllers/documents.php',
    'admin/metrics' => '../controllers/admin.php',
    'admin/audit' => '../controllers/admin.php',
    'admin/users-api' => '../controllers/admin.php',
    'admin/settings-api' => '../controllers/admin.php',
    'admin/overview' => '../views/admin/overview.php',
    'admin/users' => '../views/admin/users.php',
    'admin/settings' => '../views/admin/settings.php',
    'admin/audit-log' => '../views/admin/audit.php',
    'admin/users-update' => '../controllers/admin.php',
    'admin/settings-update' => '../controllers/admin.php',
    'patient/records' => '../views/patient/records.php',
    'patient/consents' => '../views/patient/consents.php',
    'patient/requests' => '../views/patient/requests.php',
    'patient/deregister' => '../views/patient/deregister.php',
    'patient/deregister-action' => '../controllers/patient.php',
    'patient/deletion-receipt' => '../controllers/patient.php',
    'doctor/patients' => '../views/doctor/patients.php',
    'doctor/search' => '../views/doctor/search.php',
    'doctor/assigned-records' => '../views/doctor/assigned-records.php',
    'doctor/search-patient' => '../controllers/doctor.php',
    'doctor/patient-records' => '../views/doctor/patient-records.php',
    'doctor/patient-records-api' => '../controllers/doctor.php',
    'doctor/assigned-records-api' => '../controllers/doctor.php',
    'patient/search-doctor' => '../controllers/patient.php',
    'profile' => '../views/profile.php',
    'profile/update' => '../controllers/profile.php',
    'profile/change-password' => '../controllers/profile.php',
    'settings' => '../controllers/settings.php',
    // Convenience route for GET logout
    'logout' => '../views/logout.php'
];

// Check if route exists
if (isset($routes[$route])) {
    $file = $routes[$route];
    if (file_exists($file)) {
        include $file;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid route']);
}
?>
