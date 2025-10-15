<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'auth/register':
        handleRegister();
        break;
    case 'auth/login':
        handleLogin();
        break;
    case 'auth/logout':
        handleLogout();
        break;
    case 'me':
        handleMe();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleRegister() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $email = sanitizeInput($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = sanitizeInput($data['role'] ?? '');
    $fullName = sanitizeInput($data['full_name'] ?? '');
    
    if (empty($email) || empty($password) || empty($role) || empty($fullName)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    
    if (!validateEmail($email)) {
        jsonResponse(['error' => 'Invalid email format'], 400);
    }
    
    if (!in_array($role, ['PATIENT', 'DOCTOR', 'ADMIN'])) {
        jsonResponse(['error' => 'Invalid role'], 400);
    }
    
    if (strlen($password) < 8) {
        jsonResponse(['error' => 'Password must be at least 8 characters'], 400);
    }
    
    global $pdo;
    
    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email already registered'], 409);
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $passwordHash, $role, $fullName]);
        $userId = $pdo->lastInsertId();
        
        // Create role-specific record
        if ($role === 'PATIENT') {
            $stmt = $pdo->prepare("INSERT INTO patients (user_id) VALUES (?)");
            $stmt->execute([$userId]);
        } elseif ($role === 'DOCTOR') {
            $regNo = sanitizeInput($data['reg_no'] ?? '');
            $organization = sanitizeInput($data['organization'] ?? '');
            $stmt = $pdo->prepare("INSERT INTO doctors (user_id, reg_no, organization) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $regNo, $organization]);
        }
        
        $pdo->commit();
        
        // Log registration
        writeAudit($userId, null, 'USER_REGISTER', "Role: $role, Email: $email");
        
        jsonResponse(['message' => 'Registration successful', 'user_id' => $userId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
    }
}

function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitizeInput($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        jsonResponse(['error' => 'Email and password are required'], 400);
    }
    
    // Check rate limit
    if (!checkRateLimit('login', 5, 300)) {
        jsonResponse(['error' => 'Too many login attempts. Please try again later.'], 429);
    }
    
    global $pdo;
    
    try {
        // Get user
        $stmt = $pdo->prepare("SELECT id, email, password_hash, role, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            writeAudit(null, null, 'LOGIN_FAILED', "Email: $email");
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        
        // Log successful login
        writeAudit($user['id'], null, 'LOGIN_SUCCESS', "Email: $email");
        
        jsonResponse([
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'full_name' => $user['full_name']
            ]
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Login failed: ' . $e->getMessage()], 500);
    }
}

function handleLogout() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    if (isLoggedIn()) {
        writeAudit($_SESSION['user_id'], null, 'LOGOUT', '');
    }
    
    session_destroy();
    jsonResponse(['message' => 'Logged out successfully']);
}

function handleMe() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $user = getCurrentUser();
    if (!$user) {
        jsonResponse(['error' => 'User not found'], 404);
    }
    
    // Remove sensitive data
    unset($user['password_hash']);
    
    jsonResponse(['user' => $user, 'role' => $user['role']]);
}
?>
