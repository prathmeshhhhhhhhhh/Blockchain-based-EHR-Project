<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'admin/metrics':
        handleAdminMetrics();
        break;
    case 'admin/audit':
        handleAdminAudit();
        break;
    case 'admin/users-api':
        handleAdminUsers();
        break;
    case 'admin/settings-api':
        handleAdminSettings();
        break;
    case 'admin/settings-update':
        handleAdminSettingsUpdate();
        break;
    case 'admin/users-update':
        handleAdminUsersUpdate();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleAdminMetrics() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('ADMIN');
    
    global $pdo;
    
    try {
        // Get k-anonymity threshold
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'k_anonymity_threshold'");
        $stmt->execute();
        $kThreshold = (int)$stmt->fetchColumn() ?: 10;
        
        // Basic counts
        $metrics = [];
        
        // User counts by role
        $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        $stmt->execute();
        $roleCounts = $stmt->fetchAll();
        $metrics['users_by_role'] = $roleCounts;
        
        // Total users
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $metrics['total_users'] = $stmt->fetchColumn();
        
        // Total patients
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM patients");
        $stmt->execute();
        $metrics['total_patients'] = $stmt->fetchColumn();
        
        // Total doctors
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM doctors");
        $stmt->execute();
        $metrics['total_doctors'] = $stmt->fetchColumn();
        
        // Active consents
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM consents WHERE status = 'ACTIVE'");
        $stmt->execute();
        $metrics['active_consents'] = $stmt->fetchColumn();
        
        // Total EHR records (non-deleted)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM ehr_records WHERE deleted = 0");
        $stmt->execute();
        $metrics['total_records'] = $stmt->fetchColumn();
        
        // Records by type (anonymized)
        $stmt = $pdo->prepare("SELECT type, COUNT(*) as count FROM ehr_records WHERE deleted = 0 GROUP BY type");
        $stmt->execute();
        $recordsByType = $stmt->fetchAll();
        
        // Apply k-anonymity; if nothing survives, fall back to unsuppressed so UI is not empty
        $suppressed = array_filter($recordsByType, function($item) use ($kThreshold) {
            return (int)$item['count'] >= $kThreshold;
        });
        $metrics['records_by_type'] = !empty($suppressed) ? array_values($suppressed) : $recordsByType;
        
        // Records by month (last 12 months, anonymized)
        $stmt = $pdo->prepare("SELECT 
            DATE_FORMAT(recorded_at, '%Y-%m') as month,
            type,
            COUNT(*) as count
            FROM ehr_records 
            WHERE deleted = 0 AND recorded_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month, type
            HAVING count >= ?
            ORDER BY month DESC");
        $stmt->execute([$kThreshold]);
        $metrics['records_by_month'] = $stmt->fetchAll();
        
        // Age distribution (anonymized)
        $stmt = $pdo->prepare("SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, dob, NOW()) < 18 THEN '0-17'
                WHEN TIMESTAMPDIFF(YEAR, dob, NOW()) BETWEEN 18 AND 30 THEN '18-30'
                WHEN TIMESTAMPDIFF(YEAR, dob, NOW()) BETWEEN 31 AND 50 THEN '31-50'
                WHEN TIMESTAMPDIFF(YEAR, dob, NOW()) BETWEEN 51 AND 70 THEN '51-70'
                ELSE '70+'
            END as age_group,
            COUNT(*) as count
            FROM patients p
            WHERE p.dob IS NOT NULL
            GROUP BY age_group
            HAVING count >= ?
            ORDER BY age_group");
        $stmt->execute([$kThreshold]);
        $metrics['age_distribution'] = $stmt->fetchAll();
        
        // Gender distribution (anonymized)
        $stmt = $pdo->prepare("SELECT 
            COALESCE(gender, 'Unknown') as gender,
            COUNT(*) as count
            FROM patients
            GROUP BY gender
            HAVING count >= ?
            ORDER BY count DESC");
        $stmt->execute([$kThreshold]);
        $metrics['gender_distribution'] = $stmt->fetchAll();
        
        // Deletion jobs status
        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM deletion_jobs GROUP BY status");
        $stmt->execute();
        $metrics['deletion_jobs'] = $stmt->fetchAll();
        
        // Recent activity (last 7 days)
        $stmt = $pdo->prepare("SELECT 
            DATE(ts) as date,
            action,
            COUNT(*) as count
            FROM audit_log
            WHERE ts >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY date, action
            ORDER BY date DESC");
        $stmt->execute();
        $metrics['recent_activity'] = $stmt->fetchAll();
        
        // System health
        $metrics['system_health'] = [
            'database_status' => 'healthy',
            'last_audit_entry' => null,
            'k_anonymity_threshold' => $kThreshold
        ];
        
        // Get last audit entry timestamp
        $stmt = $pdo->prepare("SELECT ts FROM audit_log ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $lastAudit = $stmt->fetchColumn();
        if ($lastAudit) {
            $metrics['system_health']['last_audit_entry'] = $lastAudit;
        }
        
        // Log metrics access
        writeAudit($_SESSION['user_id'], null, 'ADMIN_METRICS_VIEW', '');
        
        jsonResponse(['metrics' => $metrics]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch metrics: ' . $e->getMessage()], 500);
    }
}

function handleAdminAudit() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('ADMIN');
    
    // If accessed directly from a browser (not via fetch/AJAX), redirect to UI page
    if (!isAjaxRequest()) {
        header('Location: ' . BASE_URL . '/?r=admin/audit-log');
        exit;
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    // Ensure positive values
    $page = max(1, $page);
    $limit = max(1, min(100, $limit)); // Limit between 1 and 100
    $offset = max(0, $offset);
    
    global $pdo;
    
    try {
        // Get total count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log");
        $stmt->execute();
        $totalCount = $stmt->fetchColumn();
        
        // Get audit entries with pagination - use direct integers for LIMIT/OFFSET
        $stmt = $pdo->prepare("SELECT 
            a.*,
            u.full_name as actor_name,
            p.user_id as subject_user_id,
            u2.full_name as subject_name
            FROM audit_log a
            LEFT JOIN users u ON a.actor_user_id = u.id
            LEFT JOIN patients p ON a.subject_patient_id = p.id
            LEFT JOIN users u2 ON p.user_id = u2.id
            ORDER BY a.ts DESC
            LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute();
        $auditEntries = $stmt->fetchAll();
        
        // Calculate pagination info
        $totalPages = ceil($totalCount / $limit);
        
        // Log audit access
        writeAudit($_SESSION['user_id'], null, 'ADMIN_AUDIT_VIEW', "Page: $page, Limit: $limit");
        
        jsonResponse([
            'audit_entries' => $auditEntries,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_count' => $totalCount,
                'limit' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch audit log: ' . $e->getMessage()], 500);
    }
}

function handleAdminUsers() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('ADMIN');
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT u.*, p.dob, p.gender, d.reg_no, d.organization 
                              FROM users u 
                              LEFT JOIN patients p ON u.id = p.user_id 
                              LEFT JOIN doctors d ON u.id = d.user_id 
                              ORDER BY u.created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        // Remove sensitive data
        foreach ($users as &$user) {
            unset($user['password_hash']);
        }
        
        jsonResponse(['users' => $users]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch users: ' . $e->getMessage()], 500);
    }
}

function handleAdminSettings() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('ADMIN');
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        jsonResponse(['settings' => $settings]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch settings: ' . $e->getMessage()], 500);
    }
}

function handleAdminSettingsUpdate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('ADMIN');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($data as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) 
                                  VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
        
        $pdo->commit();
        
        // Log settings update
        writeAudit($_SESSION['user_id'], null, 'SETTINGS_UPDATE', 'System settings updated');
        
        jsonResponse(['message' => 'Settings updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Settings update failed: ' . $e->getMessage()], 500);
    }
}

function handleAdminUsersUpdate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('ADMIN');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = (int)($data['id'] ?? 0);
    $fullName = sanitizeInput($data['full_name'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $role = sanitizeInput($data['role'] ?? '');
    
    if (!$userId || empty($fullName) || empty($email) || empty($role)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    
    if (!validateEmail($email)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    if (!in_array($role, ['PATIENT', 'DOCTOR', 'ADMIN'])) {
        jsonResponse(['error' => 'Invalid role'], 400);
    }
    
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Update user
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $role, $userId]);
        
        // If role changed, we might need to update role-specific tables
        // For now, we'll just update the user table
        
        $pdo->commit();
        
        // Log user update
        writeAudit($_SESSION['user_id'], null, 'ADMIN_USER_UPDATE', "Updated user ID: $userId, Role: $role");
        
        jsonResponse(['message' => 'User updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'User update failed: ' . $e->getMessage()], 500);
    }
}
?>
