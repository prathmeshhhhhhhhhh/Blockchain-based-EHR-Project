<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'profile/update':
        handleUpdateProfile();
        break;
    case 'profile/change-password':
        handleChangePassword();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleUpdateProfile() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $fullName = sanitizeInput($data['full_name'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $dob = $data['dob'] ?? null;
    $gender = sanitizeInput($data['gender'] ?? null);
    $regNo = sanitizeInput($data['reg_no'] ?? null);
    $organization = sanitizeInput($data['organization'] ?? null);
    
    if (empty($fullName) || empty($email)) {
        jsonResponse(['error' => 'Name and email are required'], 400);
    }
    
    if (!validateEmail($email)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Update user table
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->execute([$fullName, $email, $_SESSION['user_id']]);
        
        // Update role-specific table
        $user = getCurrentUser();
        if ($user['role'] === 'PATIENT') {
            $stmt = $pdo->prepare("UPDATE patients SET dob = ?, gender = ? WHERE user_id = ?");
            $stmt->execute([$dob, $gender, $_SESSION['user_id']]);
        } elseif ($user['role'] === 'DOCTOR') {
            $stmt = $pdo->prepare("UPDATE doctors SET reg_no = ?, organization = ? WHERE user_id = ?");
            $stmt->execute([$regNo, $organization, $_SESSION['user_id']]);
        }
        
        $pdo->commit();
        
        // Log profile update
        writeAudit($_SESSION['user_id'], null, 'PROFILE_UPDATE', 'Profile information updated');
        
        jsonResponse(['message' => 'Profile updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Profile update failed: ' . $e->getMessage()], 500);
    }
}

function handleChangePassword() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $currentPassword = $data['current_password'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        jsonResponse(['error' => 'All password fields are required'], 400);
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonResponse(['error' => 'New passwords do not match'], 400);
    }
    
    if (strlen($newPassword) < 8) {
        jsonResponse(['error' => 'New password must be at least 8 characters'], 400);
    }
    
    global $pdo;
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            jsonResponse(['error' => 'Current password is incorrect'], 400);
        }
        
        // Update password
        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$newPasswordHash, $_SESSION['user_id']]);
        
        // Log password change
        writeAudit($_SESSION['user_id'], null, 'PASSWORD_CHANGE', 'Password changed');
        
        jsonResponse(['message' => 'Password changed successfully']);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Password change failed: ' . $e->getMessage()], 500);
    }
}
?>
