<?php
// Utility functions for MediHub

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Additional check: verify user still exists in database
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT u.*, p.dob, p.gender, d.reg_no, d.organization 
                          FROM users u 
                          LEFT JOIN patients p ON u.id = p.user_id 
                          LEFT JOIN doctors d ON u.id = d.user_id 
                          WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        } else {
            header('Location: ' . BASE_URL . '/?r=login');
            exit;
        }
    }
}

/**
 * Require specific role
 */
function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        if (isAjaxRequest()) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        } else {
            header('Location: ' . BASE_URL . '/?r=dashboard');
            exit;
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Return JSON response
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Rate limiting for login attempts
 */
function checkRateLimit($key, $maxAttempts = 5, $timeWindow = 300) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $rateKey = $key . '_' . $ip;
    
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    if (!isset($_SESSION['rate_limit'][$rateKey])) {
        $_SESSION['rate_limit'][$rateKey] = [];
    }
    
    // Clean old attempts
    $_SESSION['rate_limit'][$rateKey] = array_filter(
        $_SESSION['rate_limit'][$rateKey],
        function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        }
    );
    
    if (count($_SESSION['rate_limit'][$rateKey]) >= $maxAttempts) {
        return false;
    }
    
    $_SESSION['rate_limit'][$rateKey][] = $now;
    return true;
}

/**
 * Write audit log entry
 */
function writeAudit($actor, $patientId, $action, $details) {
    global $pdo;
    
    // Get previous hash
    $stmt = $pdo->prepare("SELECT curr_hash FROM audit_log ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $prev = $stmt->fetchColumn() ?: '';
    
    // Create payload
    $payload = json_encode([
        'actor' => $actor,
        'patient' => $patientId,
        'action' => $action,
        'ts' => time(),
        'details' => $details,
        'prev' => $prev
    ]);
    
    $curr = hash('sha256', $payload);
    
    // Insert audit log
    $stmt = $pdo->prepare("INSERT INTO audit_log(ts, actor_user_id, subject_patient_id, action, details, prev_hash, curr_hash) 
                          VALUES (NOW(), ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$actor, $patientId, $action, $details, $prev, $curr]);
    
    header("X-Audit-Hash: $curr");
    return $curr;
}

/**
 * Ensure consent for EHR access
 */
function ensureConsent($patientId, $doctorUserId, $scopesNeeded) {
    global $pdo;
    
    // Check if doctor is linked to patient
    $stmt = $pdo->prepare("SELECT l.id FROM links l 
                          JOIN doctors d ON l.doctor_id = d.id 
                          WHERE l.patient_id = ? AND d.user_id = ? AND l.status = 'APPROVED'");
    $stmt->execute([$patientId, $doctorUserId]);
    if (!$stmt->fetch()) {
        return false;
    }
    
    // Check active consent
    $stmt = $pdo->prepare("SELECT c.*, GROUP_CONCAT(cs.scope) as scopes 
                          FROM consents c 
                          JOIN consent_scopes cs ON c.id = cs.consent_id 
                          WHERE c.patient_id = ? AND c.doctor_id = (SELECT id FROM doctors WHERE user_id = ?) 
                          AND c.status = 'ACTIVE' AND c.start_at <= NOW() AND c.end_at >= NOW()
                          GROUP BY c.id");
    $stmt->execute([$patientId, $doctorUserId]);
    $consent = $stmt->fetch();
    
    if (!$consent) {
        return false;
    }
    
    $allowedScopes = explode(',', $consent['scopes']);
    foreach ($scopesNeeded as $scope) {
        if (!in_array($scope, $allowedScopes)) {
            return false;
        }
    }
    
    // Check max views
    if ($consent['max_views'] && $consent['used_views'] >= $consent['max_views']) {
        return false;
    }
    
    // Increment used views
    if ($consent['max_views']) {
        $stmt = $pdo->prepare("UPDATE consents SET used_views = used_views + 1 WHERE id = ?");
        $stmt->execute([$consent['id']]);
    }
    
    return true;
}

/**
 * Generate file hash
 */
function generateFileHash($filePath) {
    return hash_file('sha256', $filePath);
}

/**
 * Create upload directory
 */
function createUploadDir($recordId) {
    $uploadDir = "../uploads/$recordId";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    return $uploadDir;
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload error'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File too large'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    return ['valid' => true];
}
?>
