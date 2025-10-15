<?php
require_once '../config/db.php';
require_once '../config/functions.php';

requireAuth();

// Redirect to appropriate settings page based on user role
if (hasRole('ADMIN')) {
    header('Location: ' . BASE_URL . '/?r=admin/settings');
} else {
    // For patients and doctors, redirect to profile page
    header('Location: ' . BASE_URL . '/?r=profile');
}
exit;
?>
