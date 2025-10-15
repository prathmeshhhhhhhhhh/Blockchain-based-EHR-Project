<?php
/**
 * Application Configuration
 */

// Base URL configuration - Fixed for XAMPP setup
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// For XAMPP setup, we need to manually set the base path
// This should be the path from document root to the public directory
$base_path = '/EHR/mediHub-mvp/public';

// Set the base URL
$base_url = $protocol . '://' . $host . $base_path;

// Define constants for easy use
define('BASE_URL', $base_url);
define('APP_NAME', 'MediHub');
define('APP_VERSION', '1.0.0');

// Helper function to generate URLs
function url($path = '') {
    global $base_url;
    $path = ltrim($path, '/');
    return $base_url . ($path ? '/' . $path : '');
}

// Helper function to generate route URLs
function route($route) {
    return url('?r=' . $route);
}
?>
