<?php
require_once '../config/db.php';
require_once '../config/functions.php';

$action = $_GET['r'] ?? '';

switch ($action) {
    case 'links/request':
        handleRequestLink();
        break;
    case 'links/approve':
        handleApproveLink();
        break;
    case 'links/list':
        handleListLinks();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function handleRequestLink() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('DOCTOR');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $patientId = (int)($data['patientId'] ?? 0);
    
    if (!$patientId) {
        jsonResponse(['error' => 'Patient ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Verify patient exists; accept either patients.id or users.id for convenience
        $stmt = $pdo->prepare("SELECT p.id, u.full_name FROM patients p 
                              JOIN users u ON p.user_id = u.id 
                              WHERE p.id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            // Maybe client sent users.id; map it to patients.id
            $stmt = $pdo->prepare("SELECT p.id, u.full_name FROM patients p 
                                  JOIN users u ON p.user_id = u.id 
                                  WHERE p.user_id = ?");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch();
            if ($patient) {
                $patientId = (int)$patient['id'];
            }
        }
        
        if (!$patient) {
            jsonResponse(['error' => 'Patient not found'], 404);
        }
        
        // Get doctor ID
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $doctor = $stmt->fetch();
        
        if (!$doctor) {
            jsonResponse(['error' => 'Doctor profile not found'], 404);
        }
        
        // Check if link already exists
        $stmt = $pdo->prepare("SELECT id, status FROM links WHERE patient_id = ? AND doctor_id = ?");
        $stmt->execute([$patientId, $doctor['id']]);
        $existingLink = $stmt->fetch();
        
        if ($existingLink) {
            if ($existingLink['status'] === 'REQUESTED') {
                jsonResponse(['error' => 'Request already pending'], 409);
            } elseif ($existingLink['status'] === 'APPROVED') {
                jsonResponse(['error' => 'Access already approved'], 409);
            }
        }
        
        // Create link request
        $stmt = $pdo->prepare("INSERT INTO links (patient_id, doctor_id, status) VALUES (?, ?, 'REQUESTED')");
        $stmt->execute([$patientId, $doctor['id']]);
        $linkId = $pdo->lastInsertId();
        
        // Create notification for patient
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, data) 
                              SELECT p.user_id, 'ACCESS_REQUEST', 'New Access Request', 
                              CONCAT('Dr. ', u.full_name, ' has requested access to your medical records'), 
                              JSON_OBJECT('link_id', ?, 'doctor_name', u.full_name)
                              FROM patients p, users u 
                              WHERE p.id = ? AND u.id = ?");
        $stmt->execute([$linkId, $patientId, $_SESSION['user_id']]);
        
        // Log request
        writeAudit($_SESSION['user_id'], $patientId, 'ACCESS_REQUEST', "Doctor requested access to patient");
        
        jsonResponse(['message' => 'Access request sent', 'link_id' => $linkId]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Request failed: ' . $e->getMessage()], 500);
    }
}

function handleApproveLink() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireRole('PATIENT');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $linkId = (int)($data['linkId'] ?? 0);
    $approved = $data['approved'] ?? false;
    
    if (!$linkId) {
        jsonResponse(['error' => 'Link ID is required'], 400);
    }
    
    global $pdo;
    
    try {
        // Get current user's patient ID
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $patient = $stmt->fetch();
        
        if (!$patient) {
            jsonResponse(['error' => 'Patient profile not found'], 404);
        }
        
        // Get link details
        $stmt = $pdo->prepare("SELECT l.*, u.full_name as doctor_name 
                              FROM links l 
                              JOIN doctors d ON l.doctor_id = d.id 
                              JOIN users u ON d.user_id = u.id 
                              WHERE l.id = ? AND l.patient_id = ?");
        $stmt->execute([$linkId, $patient['id']]);
        $link = $stmt->fetch();
        
        if (!$link) {
            jsonResponse(['error' => 'Link not found'], 404);
        }
        
        if ($link['status'] !== 'REQUESTED') {
            jsonResponse(['error' => 'Link is not in requested status'], 400);
        }
        
        // Update link status
        $newStatus = $approved ? 'APPROVED' : 'REVOKED';
        $stmt = $pdo->prepare("UPDATE links SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $linkId]);
        
        // Create notification for doctor
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, data) 
                              SELECT d.user_id, 'ACCESS_RESPONSE', 'Access Request Response', 
                              CONCAT('Your access request has been ', LOWER(?)), 
                              JSON_OBJECT('link_id', ?, 'patient_name', u.full_name, 'approved', ?)
                              FROM doctors d, users u 
                              WHERE d.id = ? AND u.id = ?");
        $stmt->execute([$newStatus, $linkId, $approved, $link['doctor_id'], $_SESSION['user_id']]);
        
        // Log response
        writeAudit($_SESSION['user_id'], $patient['id'], 'ACCESS_RESPONSE', 
                  "Patient $newStatus access request from Dr. {$link['doctor_name']}");
        
        jsonResponse(['message' => "Access request $newStatus", 'status' => $newStatus]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Approval failed: ' . $e->getMessage()], 500);
    }
}

function handleListLinks() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    
    requireAuth();
    
    $by = $_GET['by'] ?? '';
    if (!in_array($by, ['doctor', 'patient'])) {
        jsonResponse(['error' => 'Invalid by parameter'], 400);
    }
    
    global $pdo;
    
    try {
        if ($by === 'doctor') {
            requireRole('DOCTOR');
            
            // Get doctor ID
            $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $doctor = $stmt->fetch();
            
            if (!$doctor) {
                jsonResponse(['error' => 'Doctor profile not found'], 404);
            }
            
            // Get links for this doctor
            $stmt = $pdo->prepare("SELECT l.*, u.full_name as patient_name, u.email as patient_email
                                  FROM links l 
                                  JOIN patients p ON l.patient_id = p.id 
                                  JOIN users u ON p.user_id = u.id 
                                  WHERE l.doctor_id = ? 
                                  ORDER BY l.created_at DESC");
            $stmt->execute([$doctor['id']]);
            $links = $stmt->fetchAll();
            
        } else { // patient
            requireRole('PATIENT');
            
            // Get patient ID
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $patient = $stmt->fetch();
            
            if (!$patient) {
                jsonResponse(['error' => 'Patient profile not found'], 404);
            }
            
            // Get links for this patient
            $stmt = $pdo->prepare("SELECT l.*, u.full_name as doctor_name, u.email as doctor_email, d.reg_no, d.organization
                                  FROM links l 
                                  JOIN doctors d ON l.doctor_id = d.id 
                                  JOIN users u ON d.user_id = u.id 
                                  WHERE l.patient_id = ? 
                                  ORDER BY l.created_at DESC");
            $stmt->execute([$patient['id']]);
            $links = $stmt->fetchAll();
        }
        
        jsonResponse(['links' => $links]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => 'Failed to fetch links: ' . $e->getMessage()], 500);
    }
}
?>
