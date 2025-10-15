<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'consents/create':
        handleCreateConsent();
        break;
    case 'consents/revoke':
        handleRevokeConsent();
        break;
    case 'consents/list':
        handleListConsents();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleCreateConsent() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('PATIENT');
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $patientId = (int)($data['patientId'] ?? 0);
    $doctorId = (int)($data['doctorId'] ?? 0);
    $purpose = sanitizeInput($data['purpose'] ?? '');
    $startAt = $data['startAt'] ?? '';
    $endAt = $data['endAt'] ?? '';
    $maxViews = (int)($data['maxViews'] ?? 0);
    $scopes = $data['scopes'] ?? [];
    
    // Validation
    if (!$patientId || !$doctorId || empty($purpose) || empty($startAt) || empty($endAt) || empty($scopes)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }
    
    if (!in_array($purpose, ['TREATMENT', 'RESEARCH', 'EMERGENCY'])) {
        jsonResponse(['error' => 'Invalid purpose'], 400);
    }
    
    $validScopes = ['DEMOGRAPHICS', 'ENCOUNTERS', 'LABS', 'PRESCRIPTIONS', 'NOTES', 'DOCUMENTS'];
    foreach ($scopes as $scope) {
        if (!in_array($scope, $validScopes)) {
            jsonResponse(['error' => 'Invalid scope: ' . $scope], 400);
        }
    }
    
    // Validate dates
    $startDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $startAt);
    $endDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $endAt);
    
    if (!$startDateTime || !$endDateTime) {
        jsonResponse(['error' => 'Invalid date format'], 400);
    }
    
    if ($endDateTime <= $startDateTime) {
        jsonResponse(['error' => 'End date must be after start date'], 400);
    }
    
    global $pdo;
    
    try {
        // Verify patient owns this consent
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND user_id = ?");
        $stmt->execute([$patientId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Unauthorized'], 403);
        }
        
        // Verify doctor exists and is linked
        $stmt = $pdo->prepare("SELECT d.id FROM doctors d 
                              JOIN links l ON d.id = l.doctor_id 
                              WHERE d.id = ? AND l.patient_id = ? AND l.status = 'APPROVED'");
        $stmt->execute([$doctorId, $patientId]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Doctor not found or not approved'], 404);
        }
        
        // Check for existing active consent
        $stmt = $pdo->prepare("SELECT id FROM consents 
                              WHERE patient_id = ? AND doctor_id = ? AND status = 'ACTIVE' 
                              AND start_at <= ? AND end_at >= ?");
        $stmt->execute([$patientId, $doctorId, $endAt, $startAt]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Active consent already exists for this period'], 409);
        }
        
        $pdo->beginTransaction();
        
        // Create consent
        $stmt = $pdo->prepare("INSERT INTO consents (patient_id, doctor_id, purpose, start_at, end_at, max_views) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patientId, $doctorId, $purpose, $startAt, $endAt, $maxViews ?: null]);
        $consentId = $pdo->lastInsertId();
        
        // Add scopes (simplified for now - store as JSON in consents table)
        $scopesJson = json_encode($scopes);
        $stmt = $pdo->prepare("UPDATE consents SET scopes = ? WHERE id = ?");
        $stmt->execute([$scopesJson, $consentId]);
        
        $pdo->commit();
        
        // Create notification for doctor
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, data) 
                              SELECT d.user_id, 'CONSENT_CREATED', 'New Consent Granted', 
                              CONCAT('Patient has granted you consent for ', ?, ' with scopes: ', ?), 
                              JSON_OBJECT('consent_id', ?, 'patient_id', ?, 'purpose', ?, 'scopes', ?)
                              FROM doctors d 
                              WHERE d.id = ?");
        $stmt->execute([$purpose, implode(',', $scopes), $consentId, $patientId, $purpose, json_encode($scopes), $doctorId]);
        
        // Log consent creation
        writeAudit($_SESSION['user_id'], $patientId, 'CONSENT_CREATED', 
                  "Purpose: $purpose, Scopes: " . implode(',', $scopes));
        
        jsonResponse(['message' => 'Consent created successfully', 'consent_id' => $consentId]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Consent creation failed: ' . $e->getMessage()], 500);
    }
}

function handleRevokeConsent() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $data = json_decode(file_get_contents('php://input'), true);
    $consentId = (int)($data['consentId'] ?? 0);
    
    if (!$consentId) {
        jsonResponse(['error' => 'Consent ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Get consent details
        $stmt = $pdo->prepare("SELECT c.*, p.user_id as patient_user_id, d.user_id as doctor_user_id 
                              FROM consents c 
                              JOIN patients p ON c.patient_id = p.id 
                              JOIN doctors d ON c.doctor_id = d.id 
                              WHERE c.id = ?");
        $stmt->execute([$consentId]);
        $consent = $stmt->fetch();
        
        if (!$consent) {
            jsonResponse(['error' => 'Consent not found'], 404);
        }
        
        // Check authorization (patient or doctor can revoke)
        if ($_SESSION['user_id'] != $consent['patient_user_id'] && 
            $_SESSION['user_id'] != $consent['doctor_user_id']) {
            jsonResponse(['error' => 'Unauthorized'], 403);
        }
        
        if ($consent['status'] !== 'ACTIVE') {
            jsonResponse(['error' => 'Consent is not active'], 400);
        }
        
        // Revoke consent
        $stmt = $pdo->prepare("UPDATE consents SET status = 'REVOKED' WHERE id = ?");
        $stmt->execute([$consentId]);
        
        // Create notification
        $notifyUserId = ($_SESSION['user_id'] == $consent['patient_user_id']) ? 
                       $consent['doctor_user_id'] : $consent['patient_user_id'];
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, data) 
                              VALUES (?, 'CONSENT_REVOKED', 'Consent Revoked', 
                              'A consent has been revoked', JSON_OBJECT('consent_id', ?))");
        $stmt->execute([$notifyUserId, $consentId]);
        
        // Log revocation
        writeAudit($_SESSION['user_id'], $consent['patient_id'], 'CONSENT_REVOKED', 
                  "Consent ID: $consentId");
        
        jsonResponse(['message' => 'Consent revoked successfully']);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Consent revocation failed: ' . $e->getMessage()], 500);
    }
}

function handleListConsents() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $patientId = (int)($_GET['patientId'] ?? 0);
    $doctorId = (int)($_GET['doctorId'] ?? 0);
    
    global $pdo;
    
    try {
        $whereConditions = [];
        $params = [];
        
        if ($patientId) {
            $whereConditions[] = "c.patient_id = ?";
            $params[] = $patientId;
        }
        
        if ($doctorId) {
            $whereConditions[] = "c.doctor_id = ?";
            $params[] = $doctorId;
        }
        
        // If user is patient, only show their consents
        if (hasRole('PATIENT')) {
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $patient = $stmt->fetch();
            
            if ($patient) {
                $whereConditions[] = "c.patient_id = ?";
                $params[] = $patient['id'];
            }
        }
        
        // If user is doctor, only show consents for their patients
        if (hasRole('DOCTOR')) {
            $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $doctor = $stmt->fetch();
            
            if ($doctor) {
                $whereConditions[] = "c.doctor_id = ?";
                $params[] = $doctor['id'];
            }
        }
        
        // Build the WHERE clause
        $whereClause = "";
        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        }
        
        $sql = "SELECT c.*, 
                       u1.full_name as patient_name, u1.email as patient_email,
                       u2.full_name as doctor_name, u2.email as doctor_email
                FROM consents c 
                JOIN patients p ON c.patient_id = p.id 
                JOIN users u1 ON p.user_id = u1.id 
                JOIN doctors d ON c.doctor_id = d.id 
                JOIN users u2 ON d.user_id = u2.id 
                $whereClause 
                ORDER BY c.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $consents = $stmt->fetchAll();
        
        // Process scopes from JSON or set default
        foreach ($consents as &$consent) {
            if (!empty($consent['scopes'])) {
                $consent['scopes'] = json_decode($consent['scopes'], true) ?: ['DEMOGRAPHICS', 'ENCOUNTERS'];
            } else {
                $consent['scopes'] = ['DEMOGRAPHICS', 'ENCOUNTERS']; // Default scopes
            }
        }
        
        jsonResponse(['consents' => $consents]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch consents: ' . $e->getMessage()], 500);
    }
}
?>
